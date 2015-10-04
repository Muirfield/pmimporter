<?php
namespace pmimporter\mcpe020;
use pmimporter\LevelFormat;
use pmsrc\utils\Binary;
use pmimporter\LevelFormatManager;
use pmsrc\math\Vector3;
use pmsrc\nbt\NBT;

class McPe020 implements LevelFormat {
	protected $path;
	protected $levelData;

	public static function getFormatName() { return "mcpe0.2.0"; }
	public static function isValid($path) {
		if (file_exists($path."/level.dat") && file_exists($path."/chunks.dat")) {
			$dat = file_get_contents($path.'/level.dat');
			if ((Binary::readLInt(substr($dat,0,4)) == 2
				  || Binary::readLInt(substr($dat,0,4)) == 3)
				 && Binary::readLInt(substr($dat,4,4)) == (strlen($dat) - 8))
				return true;
		}
		return false;
	}
	public static function getProviderOrder() {
		return self::ORDER_ZXY;
	}
	public static function writeable() {
		return false;
	}
	public function __construct($path, $settings = null) {
		if (file_exists($path)) {
			if (!is_dir($path)) die("$path: Already exists!\n");
			$fmt = LevelFormatManager::getFormat($path);
			if ($fmt !== null) {
				if ($fmt != self::class) die("$path: format mismatch\n");
				$this->path = $path;
				// Read nbt
				$nbt = new NBT(NBT::LITTLE_ENDIAN);
				$nbt->read(substr(file_get_contents($path."/level.dat"), 8));
				$this->levelData = $nbt->getData();
				return;
			}
			die("$path: format error\n");
		}
		die("$path: Does not exist\n");
	}
	public function getGenerator() {
		return $this->levelData["generatorName"];
	}
	public function getPresets() {
		return $this->levelData["generatorOptions"];
	}
	public function getSeed() {
		return $this->levelData["RandomSeed"];
	}
	public function getSpawn() {
		return new Vector3((float)$this->levelData["SpawnX"],(float)$this->levelData["SpawnY"],(float)$this->levelData["SpawnZ"]);
	}
	public function getChunks() {
		$chunks = [];
		for ($x =0 ; $x < 16; $x++) {
			for ($z =0 ; $z < 16; $z++) {
				$chunks[] = [ $x, $z];
			}
		}
		return $chunks;
	}
}
