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
logging("PATH_INFO: " . $_SERVER["PATH_INFO"]);


$XML_DIR = dirname(__FILE__) . "/temp.xml";
$BUCKET_FOLDER = dirname(__FILE__) . "/buckets";

$prefix = isset($_REQUEST["prefix"]) ? $_REQUEST["prefix"] : "";
$full_path = join_paths($BUCKET_FOLDER, $_SERVER["PATH_INFO"], dirname($prefix));

logging("full_path: " . $full_path);

function create_xml($dir) {
	$xml = new SimpleXMLElement('<ListBucketResult/>');
	$file_list = getDirContents($dir);
	global $BUCKET_FOLDER, $prefix;
	for ($i = 0; $i < count($file_list); $i++) {
		$object_key = explode($BUCKET_FOLDER . $_SERVER["PATH_INFO"] . "/", $file_list[$i])[1];
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

# list object
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
		if (isEmptyDir(dirname($full_path))) {
			rmdir(dirname($full_path));
		}
	}
}

fclose($log_handle);

?>