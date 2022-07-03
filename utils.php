<?php

function join_paths() {
    $paths = array();
    foreach (func_get_args() as $arg) {
        if ($arg !== '') { $paths[] = $arg; }
    }
    return preg_replace('#/+#','/',join('/', $paths));
}

function getDirBuckets($dir, &$results = array()) {
	$files = scandir($dir);
	foreach ($files as $key => $value) {
        if ($value == "." || $value == "..") {
            continue;
        }
		$path = realpath($dir . "/" . $value);
        if (is_dir($path)) {
            $results[] = $path;
        }
    }
    return $results;
}

function getDirObjects($dir, &$results = array()) {
	$files = scandir($dir);
	foreach ($files as $key => $value) {
        if ($value == "." || $value == "..") {
            continue;
        }
		$path = realpath($dir . "/" . $value);
        if (is_dir($path)) {
            getDirObjects($path, $results);
        } else {
            $results[] = $path;
        } 
    }
    return $results;
}

function isEmptyDir($dir) {
    $res = scandir($dir);
    if ($res === false) {
        return false;
    }
    return count($res) == 2;
}

function return_file($dir) {
    header('Content-Length: ' . filesize($dir));
	ob_clean();
    flush();
    readfile($dir);
}