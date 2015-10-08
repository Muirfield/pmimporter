<?php
namespace pmimporter\generic;

use pmimporter\LevelFormat;
use pmimporter\Chunk;
use pmimporter\LevelFormatManager;
use pmimporter\RegionLoader;
use pmimporter\Lock;

use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\ByteTag;
use pmsrc\nbt\tag\CompoundTag;
use pmsrc\nbt\tag\IntTag;
use pmsrc\nbt\tag\LongTag;
use pmsrc\nbt\tag\StringTag;
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
				if (!($levelData->Data instanceof CompoundTag)) die("$path: invalid level.dat\n");

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
		$levelData = new CompoundTag("Data", [
												"hardcore"=>new ByteTag("hardcore",0),
												"initialized"=>new ByteTag("initialized",1),
												"GameType"=>new IntTag("GameType",0),
												"generatorVersion"=>new IntTag("generatorVersion",1), // 2 in MCPE
												"SpawnX" => new IntTag("SpawnX",$settings["spawn"]->x),
												"SpawnY" => new IntTag("SpawnY",$settings["spawn"]->y),
												"SpawnZ" => new IntTag("SpawnZ",$settings["spawn"]->z),
												"version" => new IntTag("version",19133),
												"DayTime" => new IntTag("DayTime",0),
												"LastPlayed" => new LongTag("LastPlayed",microtime(true)*1000),
												"RandomSeed" => new LongTag("RandomSeed",$settings["seed"]),
												"SizeOnDisk" => new LongTag("SizeOnDisk",0),
												"Time" => new LongTag("Time",0),
												"generatorName" => new StringTag("generatorName",$settings["generator"]),
												"generatorOptions" => new StringTag("generatorOptions",$settings["presets"]),
												"LevelName" => new StringTag("LevelName",basename($path)),
												"GameRules" => new CompoundTag("GameRules",[])
											]);
		$nbt = new NBT(NBT::BIG_ENDIAN);
		$nbt->setData(new CompoundTag(null,["Data"=>$levelData]));
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
		$lock = new Lock($this->path."/level.dat");
		$files = glob($this->path."/region/r.*.".$this->getFileExtension());
		foreach ($files as $f) {
			$pp = [];
			if (!preg_match('/r\.(-?\d+)\.(-?\d+)\.'.$this->getFileExtension().'$/',$f,$pp)) continue;
			list(,$rx,$rz) = $pp;
			$loader = new RegionLoader($this->path,$rx,$rz,$this->getFileExtension());
			$loader->addChunks($chunks);
		}
		unset($lock);
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
	public function putChunk($cX,$cZ,Chunk $chunk) {
		$this->writeChunk($cX,$cZ,$chunk->toBinary());
	}

	abstract protected function getFileExtension();
}
