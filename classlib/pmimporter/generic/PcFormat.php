<?php
namespace pmimporter\generic;

use pmimporter\LevelFormat;
use pmimporter\LevelFormatManager;
use pmimporter\RegionLoader;
use pmimporter\Lock;

use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\Byte;
use pmsrc\nbt\tag\Compound;
use pmsrc\nbt\tag\Int;
use pmsrc\nbt\tag\Long;
use pmsrc\nbt\tag\String;
use pmsrc\math\Vector3;

abstract class PcFormat implements LevelFormat {
	protected $path;
	protected $levelData;

	protected function initFormat($class, $path, $settings) {
		if (file_exists($path)) {
			if (!is_dir($path)) die("$path: Already exists!\n");
			$fmt = LevelFormatManager::getFormat($path);
			if ($fmt !== null) {
				if ($fmt != $class) die("$path: format mismatch\n");
				$this->path = $path;
				// Read nbt
				$nbt = new NBT(NBT::BIG_ENDIAN);
				$nbt->readCompressed(file_get_contents($path."/level.dat"));
				$levelData = $nbt->getData();
				if (!($levelData->Data instanceof Compound)) die("$path: invalid level.dat\n");

				$this->levelData = $levelData->Data;
				return;
			}
			if ($settings === null) die("$path: is not the right format\n");
			echo("$path: Initializing as ".self::getFormatName()."\n");
		} else {
			if ($settings === null) die("$path: Does not exist\n");
			if (mkdir($path,0777,true) === false) die("$path: Error creating folder\n");
		}
		$this->path = $path;
		//
		// Create a new world...
		//
		if (!file_exists($path."/region")) mkdir($path."/region",0777);
		$levelData = new Compound("Data", [
												"hardcore"=>new Byte("hardcore",0),
												"initialized"=>new Byte("initialized",1),
												"GameType"=>new Int("GameType",0),
												"generatorVersion"=>new Int("generatorVersion",1), // 2 in MCPE
												"SpawnX" => new Int("SpawnX",$settings["spawn"]->x),
												"SpawnY" => new Int("SpawnY",$settings["spawn"]->y),
												"SpawnZ" => new Int("SpawnZ",$settings["spawn"]->z),
												"version" => new Int("version",19133),
												"DayTime" => new Int("DayTime",0),
												"LastPlayed" => new Long("LastPlayed",microtime(true)*1000),
												"RandomSeed" => new Long("RandomSeed",$settings["seed"]),
												"SizeOnDisk" => new Long("SizeOnDisk",0),
												"Time" => new Long("Time",0),
												"generatorName" => new String("generatorName",$settings["generator"]),
												"generatorOptions" => new String("generatorOptions",$settings["presets"]),
												"LevelName" => new String("LevelName",basename($path)),
												"GameRules" => new Compound("GameRules",[])
											]);
		$nbt = new NBT(NBT::BIG_ENDIAN);
		$nbt->setData(new Compound(null,["Data"=>$levelData]));
		file_put_contents($path."/level.dat",$nbt->writeCompressed());
		$this->levelData = $levelData;
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
		$files = glob($this->path."/region/r.*.".$this->getFileExtension());
		foreach ($files as $f) {
			$pp = [];
			if (!preg_match('/r\.(-?\d+)\.(-?\d+)\.'.$this->getFileExtension().'$/',$f,$pp)) continue;
			list(,$rx,$rz) = $pp;
			$loader = new RegionLoader($this->path,$rx,$rz,$this->getFileExtension());
			$loader->addChunks($chunks);
		}
		return $chunks;
	}
	public function writeChunk($cX,$cZ,$data) {
		$rX = $cX >> 5; $oX = $cX & 0x1f;
		$rZ = $cZ >> 5; $oZ = $cZ & 0x1f;
		$lock = new Lock($this->path."/level.dat",LOCK_EX);
		$loader = new RegionLoader($this->path,$rX,$rZ,$this->getFileExtension());
		$loader->writeChunk($oX,$oZ,$data);
		unset($loader);
		unset($lock);
	}
	public function readChunk($cX,$cZ) {
		$rX = $cX >> 5; $oX = $cX & 0x1f;
		$rZ = $cZ >> 5; $oZ = $cZ & 0x1f;
		$lock = new Lock($this->path."/level.dat");
		$loader = new RegionLoader($this->path,$rX,$rZ,$this->getFileExtension());
		$chunk = $loader->readChunk($oX,$oZ);
		unset($lock);
		return $chunk;
	}
	abstract protected function getFileExtension();
}
