<?php
if (!defined('CLASSLIB_DIR'))
	require_once(dirname(realpath(__FILE__)).'/../classlib/autoload.php');

require_once(CLASSLIB_DIR."common.php");
use pmimporter\LevelFormatManager;
use pocketmine_1_3\pmf\PMFLevel;

use pmsrc\utils\Binary;
use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\Byte;
use pmsrc\nbt\tag\Compound;
use pmsrc\nbt\tag\Int;
use pmsrc\nbt\tag\Long;
use pmsrc\nbt\tag\String;
use pmsrc\math\Vector3;

define('CMD',array_shift($argv));

$opts = [
	"name" => false,
	"spawn" => false,
	"seed" => false,
	"generator" => false,
	"preset" => false,
];
$fixname = false;
while (count($argv)) {
	if (substr($argv[0],0,2) !== '--') break;
	if (($n = substr(array_shift($argv),2)) == "fixname") {
		$fixname = true;
		continue;
	}
	$n = explode("=",$n,2);
	if (!isset($opts[$n[0]])) die("Unknown option ".$n[0]."\n");
	if (count($n) == 1) die("Must specify a value for ".$n[0]."\n");
	$opts[$n[0]] = $n[1];
}

$wpath=array_shift($argv);
if (!isset($wpath)) die("No path specified\n");
if (!file_exists($wpath)) die("$wpath: does not exist\n");

if ($fixname) $opts["name"] = basename($wpath);

$fmt = LevelFormatManager::getFormat($wpath);
if ($fmt === null) die("$wpath: unrecognized format\n");

if (!file_exists($wpath."/level.dat") && file_exists($wpath."/level.pmf")) {
	foreach ($opts as $k=>$v) {
		if ($v !== false) {
			echo "WARNING: Format ".$fmt::getFormatName()." is a read-only format!\n";
			break;
		}
	}
	$pmf = new PMFLevel($wpath."/level.pmf");
	echo "LevelName:  ".$pmf->getData("name")."\n";
	echo "Spawn:      ".implode(",",[
		$pmf->getData("spawnX"),
		$pmf->getData("spawnY"),
		$pmf->getData("spawnZ"),
	])."\n";
	echo "RandomSeed: ".$pmf->getData("seed")."\n";
	exit;
}

$dat = file_get_contents($fpath = $wpath."/level.dat");
if ($dat === false) die("$wpath: unable to open level.dat\n");

/////////////////////////////////////////////////////////////////////////////
function modifyNbt(Compound $nbt, array $opts) {
	$changed = false;
	foreach ($opts as $k=>$v) {
		if ($v === false) continue;
		switch ($k) {
			case "name":
				if (isset($nbt->LevelName) && $nbt->LevelName->getValue() == $v) continue;
				$nbt->LevelName = new String("LevelName",$v);
				$changed = true;
				break;
			case "generator":
				if (isset($nbt->generatorName) && $nbt->generatorName->getValue() == $v) continue;
				$nbt->generatorName = new String("generatorName",$v);
				$changed = true;
				break;
			case "preset":
				if (isset($nbt->generatorOptions) && $nbt->generatorOptions->getValue() == $v) continue;
				$nbt->generatorOptions = new String("generatorOptions",$v);
				$changed = true;
				break;
			case "seed":
				if (isset($nbt->RandomSeed) && $nbt->RandomSeed->getValue() == $v) continue;
				$nbt->RandomSeed = new Long("RandomSeed",(int)$v);
				$changed = true;
				break;
			case "spawn":
				$xyz = explode(",",$v);
				if (count($xyz) != 3) die("Invalid spawn value: $v\n");
				list($x,$y,$z) = $xyz;
				if (isset($nbt->SpawnX) && $nbt->SpawnX->getValue() == $x &&
						isset($nbt->SpawnY) && $nbt->SpawnY->getValue() == $y &&
						isset($nbt->SpawnZ) && $nbt->SpawnZ->getVAlue() == $z) continue;
				$nbt->SpawnX = new Int("SpawnX",(int)$x);
				$nbt->SpawnY = new Int("SpawnY",(int)$y);
				$nbt->SpawnZ = new Int("SpawnZ",(int)$z);
				$changed = true;
				break;
			default:
				die("Internal error in ".__FILE__.",".__LINE__."\n");
		}
	}
	return $changed;
}
/////////////////////////////////////////////////////////////////////////////

if ((Binary::readLInt(substr($dat,0,4)) == 2
		|| Binary::readLInt(substr($dat,0,4)) == 3
	  || Binary::readLInt(substr($dat,0,4)) == 4)
	 && Binary::readLInt(substr($dat,4,4)) == (strlen($dat) - 8)) {
	// MCPE v0.2.0/v0.9.0 level.dat
	$nbt = new NBT(NBT::LITTLE_ENDIAN);
	$nbt->read(substr($dat,8));
	$lvdat= $nbt->getData();
	$changed = modifyNbt($lvdat,$opts);
	if ($changed) {
		echo "Updating $fpath\n";
		$nbt->setData($lvdat);
		$bin = $nbt->write();
		file_put_contents($fpath,substr($dat,0,4).Binary::writeLInt(strlen($bin)).$bin);
	}
} else {
	// MCPC Anvil/McRegion
	$nbt = new NBT(NBT::BIG_ENDIAN);
	$nbt->readCompressed($dat);
  $lvdat = $nbt->getData()->Data;
	$changed = modifyNbt($lvdat,$opts);
	if ($changed) {
		echo "Updating $fpath\n";
		$nbt->setData(new Compound(null,["Data"=>$lvdat]));
		file_put_contents($fpath,$nbt->writeCompressed());
	}
}
if (isset($lvdat->LevelName)) echo "LevelName:  ".$lvdat->LevelName->getValue()."\n";
if (isset($lvdat->SpawnX) && isset($lvdat->SpawnY) && isset($lvdat->SpawnZ))
	echo "Spawn:      ".implode(",",[
		$lvdat->SpawnX->getValue(),
		$lvdat->SpawnY->getValue(),
		$lvdat->SpawnZ->getValue(),
	])."\n";
if (isset($lvdat->generatorName)) echo "Generator:  ".$lvdat->generatorName->getValue()."\n";
if (isset($lvdat->generatorOptions)) echo "Presets:    ".$lvdat->generatorOptions->getValue()."\n";
if (isset($lvdat->RandomSeed)) echo "RandomSeed: ".$lvdat->RandomSeed->getValue()."\n";
