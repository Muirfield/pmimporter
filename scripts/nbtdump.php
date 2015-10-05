<?php
if (!defined('CLASSLIB_DIR'))
	require_once(dirname(realpath(__FILE__)).'/../classlib/autoload.php');

use pmsrc\nbt\NBT;
use pmsrc\utils\Binary;

define('CMD',array_shift($argv));
$file=array_shift($argv);
if (!isset($file)) die("No file specified\n");
if (!is_file($file)) die("$file: does not exist\n");

$dat = file_get_contents($file);

if ((Binary::readLInt(substr($dat,0,4)) == 2
		|| Binary::readLInt(substr($dat,0,4)) == 3
	  || Binary::readLInt(substr($dat,0,4)) == 4)
	 && Binary::readLInt(substr($dat,4,4)) == (strlen($dat) - 8)) {
	// MCPE v0.2.0/v0.9.0 level.dat
	echo "MCPE\n";
	$nbt = new NBT(NBT::LITTLE_ENDIAN);
	$nbt->read(substr($dat,8));
} elseif (substr($dat,0,3) == "ENT" &&  ord(substr($dat,3,1)) == 0 &&
			 Binary::readLInt(substr($dat,4,4)) == 1 &&
			 Binary::readLInt(substr($dat,8,4)) == (strlen($dat) - 12)) {
	// MCPE v0.2.0 entities.dat
	echo "MCPEv0.2.0 entities\n";
	$nbt = new NBT(NBT::LITTLE_ENDIAN);
	$nbt->read(substr($dat,12));
} else {
	// MCPC Anvil/McRegion
	echo "MCPC\n";
	$nbt = new NBT(NBT::BIG_ENDIAN);
	$nbt->readCompressed($dat);
	
}
$levelData = $nbt->getData();
print_r($levelData);
