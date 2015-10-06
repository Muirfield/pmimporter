<?php
if (ini_get('phar.readonly')) {
	$cmd = escapeshellarg(PHP_BINARY);
	$cmd .= ' -d phar.readonly=0';
	foreach ($argv as $i) {
		$cmd .= ' '.escapeshellarg($i);
	}
	passthru($cmd,$rv);
	exit($rv);
}
define('CMD',array_shift($argv));
error_reporting(E_ALL);

/*
 * Build script
 */
$version = trim(file_get_contents("version.txt"));
$sver = preg_replace('/\s*pmimporter\s*/',"",$version);
$p = new Phar('pmimporter_v'.$sver.'.phar',
				  FilesystemIterator::CURRENT_AS_FILEINFO
				  | FilesystemIterator::KEY_AS_FILENAME,
				  'pmimporter.phar');



// issue the Phar::startBuffering() method call to buffer changes made to the
// archive until you issue the Phar::stopBuffering() command
$p->startBuffering();

// set the Phar file stub
// the file stub is merely a small segment of code that gets run initially
// when the Phar file is loaded, and it always ends with a __HALT_COMPILER()

$p->setStub('<?php Phar::mapPhar();define("PMIMPORTER_VERSION","'.$sver.'"); include "phar://pmimporter.phar/main.php"; __HALT_COMPILER(); ?>');

foreach (['main.php'] as $f) {
	echo ("- $f\n");
	$p[$f] = file_get_contents($f);
}

$scripts= ["version","man"];

$help = "Available sub-commands:\n";
foreach (glob('scripts/*.php') as $fp) {
	$f = preg_replace('/^scripts\//','',$fp);
	$f = preg_replace('/\.php$/','',$f);
	$scripts[] = $f;
	echo "$fp as $f\n";
	$p["scripts/$f.php"] = file_get_contents($fp);
}
sort($scripts);
$p['scripts/help.php'] = "Available sub-commands:\n\t".
							  implode("\n\t",$scripts)."\n";

$p['scripts/version.php'] = $version."\n";
$p['scripts/man.php'] = file_get_contents('README.md');

$dirs=['classlib'];
$reqs = "<?php\n";
while(count($dirs)) {
	$d = array_shift($dirs);
	$dh = opendir($d) or die("$d: unable to open directory\n");
	while (false !== ($f = readdir($dh))) {
		if ($f == '.' || $f == '..') continue;
		$fpath = "$d/$f";
		if (is_dir($fpath)) {
			if (!is_link($fpath)) array_push($dirs,$fpath);
			continue;
		}
		if (!is_file($fpath)) continue;
		if (preg_match('/\.php$/',$f) || preg_match('/\.txt$/',$f)) {
			echo("- $fpath ($d)\n");
			$p[$fpath] = file_get_contents($fpath);
			if (preg_match('/\.php$/',$f) && $d != "classlib") {
				$reqs .= "require_once('$fpath');\n";
			}
		}
	}
	closedir($dh);
}

//$p["classlib/preload.php"] = $reqs;
// COMMENTED THIS OUT AS COMPRESSING WAS GENERATING CORRUPTED ARCHIVES!
// Won't work if we fork...
//$p->compressFiles(Phar::GZ);

//Stop buffering write requests to the Phar archive, and save changes to disk
$p->stopBuffering();
//echo "my.phar archive has been saved";
