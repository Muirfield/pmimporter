<?php
if (!defined('CLASSLIB_DIR'))
	require_once(dirname(realpath(__FILE__)).'/../classlib/autoload.php');

require_once(CLASSLIB_DIR."common.php");
use pmimporter\LevelFormatManager;
use pmimporter\Blocks;
use pmimporter\Misc;
use pmsrc\utils\Utils;
use pmsrc\math\Vector3;

define('CMD',array_shift($argv));
$opts = [
	"format" => "mcregion",
	"threads" => Utils::getCoreCount(),
	"yoff" => 0,
	"adjchunk" => false,
	"rules" => false,
];
$convert = true;
$clobber = false;
$minx = $minz = $maxx = $maxz = null;
while (count($argv) > 0) {
	if (substr($argv[0],0,2) != "--") break;
	$opt = substr(array_shift($argv),2);
	if (($val = preg_replace('/^min-x=/','',$opt)) != $opt) {
		$minx = (int)$val;
	} elseif (($val = preg_replace('/^max-x=/','',$opt)) != $opt) {
		$maxx = (int)$val;
	} elseif (($val = preg_replace('/^min-z=/','',$opt)) != $opt) {
		$minz = (int)$val;
	} elseif (($val = preg_replace('/^max-z=/','',$opt)) != $opt) {
		$maxz = (int)$val;
	} elseif (($val = preg_replace('/^x=/','',$opt)) != $opt) {
		$minx = $maxx = (int)$val;
	} elseif (($val = preg_replace('/^z=/','',$opt)) != $opt) {
		$minz = $maxz = (int)$val;
	} elseif ($opt == "convert") {
		$convert = true;
	} elseif ($opt == "no-convert") {
		$convert = false;
	} elseif ($opt == "clobber") {
		$clobber = true;
	} elseif ($opt == "no-clobber") {
		$clobber = false;
	} else {
		$opt = explode("=",$opt,2);
		if (!isset($opts[$opt[0]])) die("Invalid option: ".$opt[0]."\n");
		if (count($opt) == 1) die("Must specify a value for ".$opt[0]."\n");
		$opts[$opt[0]] = $opt[1];
	}
}
$chgX = $chgZ = 0;
if ($opts["adjchunk"] !== false) {
  $n = explode(",",$opts["adjchunk"]);
	if (count($n) != 2) die("adjchunk: Must specify X,Z chunk values\n");
	$chgX = (int)$n[0];
	$chgZ = (int)$n[1];
}
if (!extension_loaded("pcntl")) $opts["threads"] = 1;

$srcpath=array_shift($argv);
if (!isset($srcpath)) die("No src path specified\n");
$srcpath = preg_replace('/\/*$/',"",$srcpath).'/';
if (!is_dir($srcpath)) die("$srcpath: not found\n");
$srcformat = LevelFormatManager::getFormat($srcpath);
if (!$srcformat) die("$srcpath: Format not recognized\n");
$src = new $srcformat($srcpath);

$dstpath=array_shift($argv);
if (!isset($dstpath)) die("No dst path specified\n");
$dstpath = preg_replace('/\/*$/',"",$dstpath).'/';

if (is_dir($dstpath)) {
	$dstformat = LevelFormatManager::getFormat($dstpath);
	if (!$dstformat) die("$dstpath: Format not recognized\n");
	$settings = null;
}  else {
	$dstformat = LevelFormatManager::getFormatByName($opts["format"]);
	if ($dstformat === null) die("Unknown format: ".$opts["format"]."\n");
	$settings = [
		"spawn" => $src->getSpawn(),
		"seed" => $src->getSeed(),
		"generator" => $src->getGenerator(),
		"presets" => $src->getPresets(),
	];
}
if (!$dstformat::writeable()) die("Format: ".$dstformat::getFormatName()." is not an output format\n");
$dst = new $dstformat($dstpath,$settings);

if ($opts["rules"] !== false) {
	if ($convert) {
		// Add conversion rules
		$tab = Misc::readTable($opts["rules"]);
		if ($tab === false) die("Unable to read ".$opts["rules"]."\n");
		foreach ($tab as $ln) {
			Blocks::addRule((int)$ln[0],(int)$ln[1]);
		}
	} else {
		echo ("RULES ignored as no block conversion specified\n");
	}
}

//echo __FILE__.",".__LINE__."\n";//##DEBUG
if ($minx === null && $minz === null && $maxx === null && $maxz === null) {
	$chunks = $src->getChunks();
} else {
	//echo __METHOD__.",".__LINE__."\n";//##DEBUG
	$chunks = [];
	foreach ($src->getChunks() as $xz => $n) {
		list($cx,$cz) = $n;
		if ( ($minx !== null && $cx < $minx) || ($maxx !== null && $cx > $maxx) ||
			 	($minz !== null && $cz < $minz) || ($maxz !== null && $cz > $maxz) ) continue;
		$chunks[$xz] = $n;
	}
}
if (!$clobber) {
	$dstchunks = $dst->getChunks();
	foreach (array_values($chunks) as $xz) {
		list($x,$z) = $xz;
		$x += $chgX;
		$z += $chgZ;
		$id = implode(",",[$x,$z]);
		if (isset($dstchunks[implode(",",[$x,$z])])) unset($chunks[implode(",",$xz)]);
	}
	unset($dstchunks);
}
if (count($chunks) == 0) die("No chunks selected for copy\n");
echo "Number of Chunks to Copy: ".count($chunks)."\n";
// You need 3 or more chunks to start multi-threading
if (count($chunks) < 3) $opts["threads"] = 1;

//if ($opts["threads"] > 1) {
//	echo("Disabling multiprocessing : slows down!\n");
//	$opts["threads"] = 1;
//}

if ($opts["threads"] == 1) {
	foreach ($chunks as $n) {
		list($cx,$cz) = $n;
		$chunk = $src->getChunk($cx,$cz,$opts["yoff"]);
		echo ".";
		LevelFormatManager::importChunk($dst,$cx+$chgX,$cz+$chgZ,$chunk,$convert);
	}
	echo "\n";
	exit;
}

function copyNextChunk() {
	global $chunks,$workers;
	global $src,$dst;
	global $opts,$chgX,$chgZ,$convert;

	if (!count($chunks)) return;
	list($cx,$cz) = array_pop($chunks);
	$pid = pcntl_fork();

	if ($pid == 0) {
		//echo "spawned: ".getmypid()."\n";
		//echo ".";
		$chunk = $src->getChunk($cx,$cz,$opts["yoff"]);
		LevelFormatManager::importChunk($dst,$cx+$chgX,$cz+$chgZ,$chunk,$convert);
		exit(0);
	} elseif ($pid == -1) {
		die("Could not fork\n");
	} else {
		$workers[$pid] = [$cx,$cz];
	}
}

if (count($chunks)-1 < $opts["threads"]) $opts["threads"] = count($chunks)-1;
echo "Threads: ".$opts["threads"]."\n";

// Do ONE chunk in the main thread to make sure all modules are indeed in
// memory before we start forking
$total = count($chunks);
list($cx,$cz) = array_pop($chunks);
$chunk = $src->getChunk($cx,$cz,$opts["yoff"]);
LevelFormatManager::importChunk($dst,$cx+$chgX,$cz+$chgZ,$chunk,$convert);
if (count($chunks) == 0) exit;

$workers = [];
for ($c = $opts["threads"];$c--;) {
	copyNextChunk();
}
while ($pid = pcntl_wait($rstatus)) {
	echo "\r".($total-count($chunks))."/".$total." (".((int)(100*($total-count($chunks))/$total))."%) ";
	if (!isset($workers[$pid])) continue;
	list($cX,$cZ) = $workers[$pid];
	unset($workers[$pid]);
	if (pcntl_wexitstatus($rstatus)) {
		echo "$pid ($cX,$cZ) failed\n";
	//} else {
		//echo "$pid ($cX,$cZ) succesful\n";
	}
	if (count($chunks)) {
		copyNextChunk();
	} else {
		if (!count($workers)) break;
	}
}
echo "\nDONE\n";
