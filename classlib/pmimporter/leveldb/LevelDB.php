<?php
namespace pmimporter\leveldb;

use pmimporter\LevelFormat;
use pmimporter\LevelFormatManager;
//use pmimporter\generic\BaseFormat;
//use pmimporter\mcregion\RegionLoader;

use pmsrc\utils\Binary;
use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\Byte;
use pmsrc\nbt\tag\Compound;
use pmsrc\nbt\tag\Int;
use pmsrc\nbt\tag\Long;
use pmsrc\nbt\tag\String;
use pmsrc\math\Vector3;

class LevelDB implements LevelFormat {
	const ENTRY_VERSION = "v";
	const ENTRY_FLAGS = "f";
	const ENTRY_TICKS = "3";
	const ENTRY_ENTITIES = "2";
	const ENTRY_TILES = "1";
	const ENTRY_TERRAIN = "0";


	protected $path;
	protected $levelData;

	public static function chunkIndex($chunkX, $chunkZ){
		return Binary::writeLInt($chunkX) . Binary::writeLInt($chunkZ);
	}

	public static function getFormatName() {
		return "LevelDB";
	}
	public static function isValid($path) {
		return file_exists($path . "/level.dat") && is_dir($path . "/db");
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
		if (!file_exists($path."/db")) mkdir($path."/db",0777);
		$levelData = new Compound("", [
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
		$nbt->setData($levelData);
		$buffer = $nbt->write();
		file_put_contents($path."/level.dat",
											Binary::writeLInt(3).Binary::writeLInt(strlen($buffer)).
											$buffer);
		$db = new \LevelDB($path."/db/");
		$db->close();
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
		$db = new \LevelDB($this->path."/db/");
		$it = $db->getIterator();
		// Or loop in foreach
		while ($it->valid()) {
			$key = $it->key(); $it->next();
			if ($key{8} != self::ENTRY_VERSION) continue;
			$chunkX = Binary::readLInt(substr($key,0,4));
			$chunkZ = Binary::readLInt(substr($key,4,4));
			$chunks[implode(",",[$chunkX,$chunkZ])] = [ $chunkX, $chunkZ];

		}
		$db->close();
		return $chunks;
	}

}
