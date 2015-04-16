<?php
if (isset($argv[0])) array_shift($argv); // Always strip this one...
define('CLASSLIB_DIR','phar://pmimporter.phar/classlib/');

if (!isset($argv[0])) {
	echo("No sub-command specified\n");
	require_once(CLASSLIB_DIR.'autoload.php');
	die();
}

if ($argv[0] == "man") {
	if (extension_loaded("posix")) {
		if (posix_isatty(STDIN)) {
			// Interactive... pipe through more
			$fin = fopen("phar://pmimporter.phar/scripts/$argv[0].php","r");
			$fout = popen("more","w");
			if ($fin && $fout) {
				stream_copy_to_stream($fin,$fout);
				fclose($fin);
				fclose($fout);
				exit();
			}
		}
	}
	require_once("phar://pmimporter.phar/scripts/$argv[0].php");
	exit;
}

if (in_array($argv[0],array("version","plugin","help"))) {
	require_once("phar://pmimporter.phar/scripts/$argv[0].php");
	require_once(CLASSLIB_DIR.'autoload.php');
	exit;
}
require_once(CLASSLIB_DIR.'autoload.php');

if (is_readable("phar://pmimporter.phar/scripts/$argv[0].php")) {
	require_once("phar://pmimporter.phar/scripts/$argv[0].php");
} else {
	die("Unknown sub-command $argv[0].  Use \"help\"\n");
}
