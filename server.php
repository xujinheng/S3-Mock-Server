<?php

function join_paths() {
    $paths = array();
    foreach (func_get_args() as $arg) {
        if ($arg !== '') { $paths[] = $arg; }
    }
    return preg_replace('#/+#','/',join('/', $paths));
}

$XML_DIR = dirname(__FILE__) . "/temp.xml";
$BUCKET_FOLDER = dirname(__FILE__) . "/buckets";

if (isset($_REQUEST["prefix"])) {
	$full_path = join_paths($BUCKET_FOLDER, $_SERVER["PATH_INFO"], $_REQUEST["prefix"]);
} else {
	$full_path = join_paths($BUCKET_FOLDER, $_SERVER["PATH_INFO"]);
}

$LOG_DIR = dirname(__FILE__) . "/log.txt";
$log_handle=fopen($LOG_DIR, "a+");

function logging($str) {
	global $log_handle;
	fwrite($log_handle, $str . "\n");
}

logging("============================");
logging("HEADER: " . json_encode($_SERVER));
logging("URI: " . $_SERVER["REQUEST_URI"]);
logging("METHOD: " . $_SERVER["REQUEST_METHOD"]);
// logging("BODY: " . file_get_contents("php://input"));
logging("PATH_INFO: " . $_SERVER["PATH_INFO"]);
logging("full_path: " . $full_path);


function getDirContents($dir, &$results = array()) {
	$files = scandir($dir);
	foreach ($files as $key => $value) {
		$path = realpath($dir . "/" . $value);
		if (!is_dir($path)) {
			$results[] = $path;
		} else if ($value != "." && $value != "..") {
			getDirContents($path, $results);
		}
	}
	return $results;
}

function create_xml($dir) {
	$file_list = getDirContents($dir);
	$xml = new SimpleXMLElement('<ListBucketResult/>');
	// $xml->addChild();
	for ($i = 0; $i < count($file_list); $i++) {
		$Contents = $xml->addChild('Contents');
		global $BUCKET_FOLDER;
		$object_key = explode($BUCKET_FOLDER . $_SERVER["PATH_INFO"] . "/", $file_list[$i])[1];
		logging($BUCKET_FOLDER . $_SERVER["PATH_INFO"]);
		$Contents->addChild('Key', $object_key);
		$Contents->addChild('LastModified', filemtime($file_list[$i]));
		$Contents->addChild('Size', filesize($file_list[$i]));
	}
	global $XML_DIR;
	$handle=fopen($XML_DIR, "w+");
	fwrite($handle, $xml->asXML());
}

if (isset($_REQUEST["encoding-type"]) && $_REQUEST["encoding-type"] == "url") {
	logging("[list objects]");
	create_xml($full_path);
	header('Content-Length: ' . filesize($XML_DIR));
	ob_clean();
    flush();
	global $XML_DIR;
    readfile($XML_DIR);
}


# download object
else if ($_SERVER["REQUEST_METHOD"] == "HEAD" || $_SERVER["REQUEST_METHOD"] == "GET") {
	logging("[download objects]");
	header('Content-Length: ' . filesize($full_path));
	ob_clean();
    flush();
    readfile($full_path);
}

# create object
else if ($_SERVER["REQUEST_METHOD"] == "PUT") {
	logging("[create objects]");
	# create folder
	$dirname = dirname($full_path);
	if (!is_dir($dirname)) {
		mkdir($dirname, 0755, True);
	}
	# create file
	$handle=fopen($full_path, "w+");
	$write=fwrite($handle, file_get_contents("php://input"));
}

# delete object
else if ($_SERVER["REQUEST_METHOD"] == "DELETE") {
	logging("[delete objects]");
	if (is_dir($full_path)) {
		rmdir($full_path);
	} else {
		unlink($full_path);
		# handle rmdir properly
		# rmdir(dirname($full_path));
	}
	
}

fclose($log_handle);

?>