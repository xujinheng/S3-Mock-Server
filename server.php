<?php

require "./utils.php";

$LOG_DIR = dirname(__FILE__) . "/log.txt";
$log_handle=fopen($LOG_DIR, "a+");

function logging($str) {
	global $log_handle;
	fwrite($log_handle, $str . "\n");
}

logging("============================");
// logging("HEADER: " . json_encode($_SERVER));
logging("URI: " . $_SERVER["REQUEST_URI"]);
logging("METHOD: " . $_SERVER["REQUEST_METHOD"]);
// logging("BODY: " . file_get_contents("php://input"));


$XML_DIR = dirname(__FILE__) . "/temp.xml";
$BUCKET_FOLDER = dirname(__FILE__) . "/buckets";

$PATH_INFO  = isset($_SERVER["PATH_INFO"]) ? $_SERVER["PATH_INFO"] : "";
$prefix = isset($_REQUEST["prefix"]) ? $_REQUEST["prefix"] : "";
$full_path = join_paths($BUCKET_FOLDER, $PATH_INFO, dirname($prefix));

logging("PATH_INFO: " . $PATH_INFO);
logging("prefix: " . $prefix);
logging("full_path: " . $full_path);

function create_xml_buckets($dir) {
	$xml = new SimpleXMLElement('<ListAllMyBucketsResult/>');
	$Buckets = $xml->addChild('Buckets');
	$file_list = getDirBuckets($dir);
	global $BUCKET_FOLDER;
	for ($i = 0; $i < count($file_list); $i++) {
		$bucket_name = explode($BUCKET_FOLDER . "/", $file_list[$i])[1];
		$Bucket = $Buckets->addChild('Bucket');
		$Bucket->addChild('Name', $bucket_name);
		$Bucket->addChild('CreationDate', filectime($file_list[$i]));
	}
	global $XML_DIR;
	$handle = fopen($XML_DIR, "w+");
	fwrite($handle, $xml->asXML());
}

function create_xml_objects($dir) {
	$xml = new SimpleXMLElement('<ListBucketResult/>');
	$file_list = getDirObjects($dir);
	global $BUCKET_FOLDER, $PATH_INFO, $prefix;
	for ($i = 0; $i < count($file_list); $i++) {
		$object_key = explode($BUCKET_FOLDER . $PATH_INFO . "/", $file_list[$i])[1];
		# filter by prefix
		if (substr($object_key, 0, strlen($prefix)) === $prefix) {
			$Contents = $xml->addChild('Contents');
			$Contents->addChild('Key', $object_key);
			$Contents->addChild('LastModified', filemtime($file_list[$i]));
			$Contents->addChild('Size', filesize($file_list[$i]));
		}
	}
	global $XML_DIR;
	$handle = fopen($XML_DIR, "w+");
	fwrite($handle, $xml->asXML());
}

#############

# list buckets
if ($_SERVER["REQUEST_METHOD"] == "GET" && $PATH_INFO == '') {
	logging("[list buckets]");
	create_xml_buckets($full_path);
	return_file($XML_DIR);
}

# list object
else if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_REQUEST["encoding-type"]) && $_REQUEST["encoding-type"] == "url") {
	logging("[list objects]");
	create_xml_objects($full_path);
	return_file($XML_DIR);
}

# create buckets
else if ($_SERVER["REQUEST_METHOD"] == "PUT" && dirname($full_path) == $BUCKET_FOLDER && $_SERVER["CONTENT_LENGTH"] == "0") {
	logging("[create buckets]");
	mkdir($full_path, 0755, True);
}

# create objects
else if ($_SERVER["REQUEST_METHOD"] == "PUT") {
	logging("[create objects]");
	if (!is_dir(dirname($full_path))) {
		mkdir(dirname($full_path), 0755, True);
	}
	$handle=fopen($full_path, "w+");
	$write=fwrite($handle, file_get_contents("php://input"));
}

# download object
else if ($_SERVER["REQUEST_METHOD"] == "HEAD" || $_SERVER["REQUEST_METHOD"] == "GET") {
	logging("[download objects]");
	return_file($full_path);
}

# delete buckets/object
else if ($_SERVER["REQUEST_METHOD"] == "DELETE") {
	logging("[delete buckets/objects]");
	if (is_dir($full_path)) {
		rmdir($full_path);
	} else {
		unlink($full_path);
		$dirname = dirname($full_path);
		while ($dirname !== $BUCKET_FOLDER && isEmptyDir($dirname)) {
			rmdir($dirname);
			$dirname = dirname($dirname);
		}
	}
}

fclose($log_handle);

?>