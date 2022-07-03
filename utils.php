<?php

function join_paths() {
    $paths = array();
    foreach (func_get_args() as $arg) {
        if ($arg !== '') { $paths[] = $arg; }
    }
    return preg_replace('#/+#','/',join('/', $paths));
}

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

function isEmptyDir($dir) {
    $res = scandir($dir);
    if ($res === false) {
        return false;
    }
    return count($res) == 2;
}