<?php
/**
 * Final S3-compatible PHP server (Scheme A + Hidden Prefixes)
 *
 * - Anonymous allowed: ListBuckets, ListObjects
 * - Auth required: Get/Head/Put/Delete object, Create/Delete bucket
 * - Server-side denylist of hidden prefixes (e.g. _h5ai/)
 */

date_default_timezone_set("UTC");

/* ================== CONFIG ================== */

$BASE_DIR    = __DIR__;
$BUCKET_ROOT = $BASE_DIR . "/buckets";
$LOG_FILE    = $BASE_DIR . "/log.txt";

/**
 * access_key => bcrypt(secret)
 */
$S3_USERS = [
    "admin" => '$2y$10$0POnchUN.kn3igtwojyJA.0lqKvxz2LTGy0gBFhcm67SxheslLYBe',
];

/**
 * Object key prefixes that should NEVER be exposed
 * (server-side denylist)
 */
$HIDDEN_PREFIXES = [
    "_h5ai/",
    "_internal/",
    "_cache/",
    ".git/",
];

/* ================== LOG ================== */

function log_msg($msg) {
    global $LOG_FILE;
    file_put_contents($LOG_FILE, "[" . gmdate("c") . "] " . $msg . "\n", FILE_APPEND);
}

/* ================== AUTH ================== */

/**
 * Extract access_key from SigV4 header or query.
 * Returns null if no credential present.
 */
function extract_access_key() {
    // Header-style SigV4
    if (isset($_SERVER["HTTP_AUTHORIZATION"]) &&
        preg_match('/Credential=([^\/,\s]+)/', $_SERVER["HTTP_AUTHORIZATION"], $m)) {
        return $m[1];
    }
    if (isset($_SERVER["REDIRECT_HTTP_AUTHORIZATION"]) &&
        preg_match('/Credential=([^\/,\s]+)/', $_SERVER["REDIRECT_HTTP_AUTHORIZATION"], $m)) {
        return $m[1];
    }

    // Query-string SigV4 (presigned / boto3 fallback)
    if (isset($_GET["X-Amz-Credential"])) {
        $cred = urldecode($_GET["X-Amz-Credential"]);
        $parts = explode("/", $cred, 2);
        if ($parts[0] !== "") return $parts[0];
    }

    // Legacy SigV2 (rare)
    if (isset($_GET["AWSAccessKeyId"])) {
        return $_GET["AWSAccessKeyId"];
    }

    return null;
}

/**
 * Require authentication for protected operations.
 */
function require_auth() {
    global $S3_USERS;

    $access_key = extract_access_key();
    log_msg("AUTH access_key=" . ($access_key ?? "NULL"));

    if ($access_key === null || !isset($S3_USERS[$access_key])) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }

    return $access_key;
}

/* ================== HIDDEN PREFIX ================== */

function is_hidden_key($key) {
    global $HIDDEN_PREFIXES;
    foreach ($HIDDEN_PREFIXES as $hidden) {
        if (strpos($key, $hidden) === 0) {
            return true;
        }
    }
    return false;
}

/* ================== PATH ================== */

function join_paths(...$paths) {
    return preg_replace('#/+#','/', join('/', array_filter($paths)));
}

function resolve_fs_path($bucket_root, $path_info_raw) {
    $p = urldecode($path_info_raw ?? "");
    $p = str_replace("\0", "", $p);
    $p = preg_replace('#/+#', '/', $p);

    $segments = array_values(array_filter(explode("/", $p)));
    foreach ($segments as $seg) {
        if ($seg === "..") {
            http_response_code(400);
            echo "Bad Request";
            exit;
        }
    }
    return join_paths($bucket_root, join("/", $segments));
}

/* ================== RESPONSE HELPERS ================== */

function send_no_body($code = 200) {
    http_response_code($code);
    header("Content-Length: 0");
    exit;
}

function send_xml($xml) {
    header("Content-Type: application/xml; charset=UTF-8");
    header("Content-Length: " . strlen($xml));
    echo $xml;
    exit;
}

function send_object_head($path) {
    if (!file_exists($path) || is_dir($path)) {
        http_response_code(404);
        exit;
    }
    header("Content-Length: " . filesize($path));
    header("Last-Modified: " . gmdate('D, d M Y H:i:s', filemtime($path)) . " GMT");
    exit;
}

function send_object_get($path) {
    if (!file_exists($path) || is_dir($path)) {
        http_response_code(404);
        exit;
    }
    header("Content-Length: " . filesize($path));
    readfile($path);
    exit;
}

/* ================== XML BUILDERS ================== */

function list_buckets_xml($root) {
    if (!is_dir($root)) mkdir($root, 0755, true);

    $xml = new SimpleXMLElement('<ListAllMyBucketsResult/>');
    $xml->addAttribute("xmlns", "http://s3.amazonaws.com/doc/2006-03-01/");
    $b = $xml->addChild("Buckets");

    foreach (scandir($root) as $d) {
        if ($d === "." || $d === "..") continue;
        if (is_dir("$root/$d")) {
            $bk = $b->addChild("Bucket");
            $bk->addChild("Name", $d);
            $bk->addChild("CreationDate", gmdate("c", filectime("$root/$d")));
        }
    }
    return $xml->asXML();
}

function list_objects_xml($bucket, $bucket_path, $prefix) {
    $xml = new SimpleXMLElement('<ListBucketResult/>');
    $xml->addAttribute("xmlns", "http://s3.amazonaws.com/doc/2006-03-01/");
    $xml->addChild("Name", $bucket);
    $xml->addChild("Prefix", $prefix);
    $xml->addChild("IsTruncated", "false");

    if (!is_dir($bucket_path)) return $xml->asXML();

    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($bucket_path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($rii as $f) {
        if ($f->isDir()) continue;
        $key = substr($f->getPathname(), strlen($bucket_path) + 1);

        // client prefix filter
        if ($prefix && strpos($key, $prefix) !== 0) continue;

        // server-side hidden prefix filter
        if (is_hidden_key($key)) continue;

        $c = $xml->addChild("Contents");
        $c->addChild("Key", $key);
        $c->addChild("LastModified", gmdate("c", $f->getMTime()));
        $c->addChild("Size", $f->getSize());
    }
    return $xml->asXML();
}

/* ================== ENTRY ================== */

$method   = $_SERVER["REQUEST_METHOD"];
$path_raw = $_SERVER["PATH_INFO"] ?? "";
$full     = resolve_fs_path($BUCKET_ROOT, $path_raw);

$segments = array_values(array_filter(explode("/", trim(urldecode($path_raw), "/"))));
$bucket   = $segments[0] ?? "";
$bucket_path = $bucket ? join_paths($BUCKET_ROOT, $bucket) : $BUCKET_ROOT;

// object key relative to bucket
$key = (count($segments) >= 2)
    ? join("/", array_slice($segments, 1))
    : "";

log_msg("$method path=$path_raw");

/* ================== ROUTES ================== */

// ListBuckets (anonymous)
if ($method === "GET" && ($path_raw === "" || $path_raw === "/")) {
    send_xml(list_buckets_xml($BUCKET_ROOT));
}

// ListObjects (anonymous)
if ($method === "GET" && $bucket && count($segments) === 1) {
    $prefix = $_GET["prefix"] ?? "";
    // hide internal prefixes even if client asks for them
    if (is_hidden_key($prefix)) {
        send_xml('<ListBucketResult/>');
    }
    send_xml(list_objects_xml($bucket, $bucket_path, $prefix));
}

// HeadBucket (protected)
if ($method === "HEAD" && $bucket && count($segments) === 1) {
    require_auth();
    send_no_body(is_dir($bucket_path) ? 200 : 404);
}

// CreateBucket (protected)
if ($method === "PUT" && $bucket && count($segments) === 1 && intval($_SERVER["CONTENT_LENGTH"] ?? 0) === 0) {
    require_auth();
    if (!is_dir($bucket_path)) mkdir($bucket_path, 0755, true);
    send_no_body(200);
}

// HeadObject (protected)
if ($method === "HEAD" && $bucket && count($segments) >= 2) {
    if (is_hidden_key($key)) {
        http_response_code(404);
        exit;
    }
    require_auth();
    send_object_head($full);
}

// GetObject (protected)
if ($method === "GET" && $bucket && count($segments) >= 2) {
    if (is_hidden_key($key)) {
        http_response_code(404);
        exit;
    }
    require_auth();
    send_object_get($full);
}

// PutObject (protected)
if ($method === "PUT" && $bucket && count($segments) >= 2) {
    if (is_hidden_key($key)) {
        http_response_code(404);
        exit;
    }
    require_auth();
    $dir = dirname($full);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $tmp = $full . ".tmp." . bin2hex(random_bytes(6));
    file_put_contents($tmp, file_get_contents("php://input"));
    rename($tmp, $full);

    send_no_body(200);
}

// DeleteObject (protected)
if ($method === "DELETE" && $bucket && count($segments) >= 2) {
    if (is_hidden_key($key)) {
        http_response_code(404);
        exit;
    }
    require_auth();
    if (file_exists($full)) unlink($full);
    send_no_body(204);
}

http_response_code(400);
echo "Bad Request";