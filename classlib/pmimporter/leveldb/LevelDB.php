<?php
namespace pmimporter\leveldb;

use pmimporter\LevelFormat;
use pmimporter\LevelFormatManager;
use pmimporter\Lock;
use pmimporter\Shifter;

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
		$lock = new Lock($this->path."/level.dat");
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
		unset($lock);
		return $chunks;
	}

	public function getChunk($x,$z,$yoff=0) {
		$lock = new Lock($this->path."/level.dat");
		$db = new \LevelDB($this->path."/db/");
		$index = self::chunkIndex($x,$z);
		$terrain = $db->get($index.self::ENTRY_TERRAIN);
		$entityData = $db->get($index.self::ENTRY_ENTITIES);
		$tileData = $db->get($index.self::ENTRY_TILES);
		$flags = $db->get($index.self::ENTRY_FLAGS);
		$db->close();
		unset($lock);

		if ($flags === false) $flags = "\x03";
		$flags = ord($flags);

		$data = [
			"x" => $x,
			"z" => $z,
			"isGenerated" => ($flags & 0x01) > 0,
			"isPopulated" => ($flags & 0x02) > 0,
			"isLightPopulated" => ($flags & 0x04) > 0,
		];
		$reader = new NBT(NBT::LITTLE_ENDIAN);
		if ($entityData !== false && strlen($entityData) > 0) {
			$reader->read($entityData,true);
			$data["entities"] = $reader->getData();
			if (!is_array($data["entities"])) $data["entities"] = [$data["entities"]];
			if ($yoff != 0) $data["entities"] = Shifter::entities($data["entities"],$yoff);
		}
		if ($tileData !== false) {
			$reader->read($tileData,true);
			$data["tiles"] = $reader->getData();
			if (!is_array($data["tiles"])) $data["tiles"] = [$data["tiles"]];
			if ($yoff != 0) $data["tiles"] = Shifter::entities($data["$tiles"],$yoff);
		}

		if ($terrain !== false) {
			$offset = 0;
			$data["blocks"] = substr($terrain, $offset, 32768);
			$offset += 32768;
			$data["meta"] = substr($terrain, $offset, 16384);
			$offset += 16384;
			$data["skyLight"] = substr($terrain, $offset, 16384);
			$offset += 16384;
			$data["blockLight"] = substr($terrain, $offset, 16384);
			$offset += 16384;
			if ($yoff < 0) {
				$yoff = -$yoff;
				$data["blocks"] = Shifter::down($data["blocks"],"\x00",$yoff,PM_HEIGHT_BITS);
				if (($yoff & 1) == 0) {
					$data["meta"] = Shifter::down($data["meta"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
					$data["blockLight"] = Shifter::down($data["blockLight"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
					$data["skyLight"] = Shifter::down($data["skyLight"],"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
				} else {
					$data["meta"] = Shifter::nibbleDown($data["meta"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
					$data["blockLight"] = Shifter::nibbleDown($data["blockLight"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
					$data["skyLight"] = Shifter::nibbleDown($data["skyLight"],"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
				}
				$yoff = -$yoff;
			} elseif ($yoff > 0) {
				$data["blocks"] = Shifter::up($data["blocks"],"\x00",$yoff,PM_HEIGHT_BITS);
				if (($yoff & 1) == 0) {
					$data["meta"] = Shifter::up($data["meta"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
					$data["blockLight"] = Shifter::up($data["blockLight"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
					$data["skyLight"] = Shifter::up($data["skyLight"],"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
				} else {
					$data["meta"] = Shifter::nibbleUp($data["meta"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
					$data["blockLight"] = Shifter::nibbleUp($data["blockLight"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
					$data["skyLight"] = Shifter::nibbleUp($data["skyLight"],"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
				}

			}
			$data["heightMap"] = [];
			foreach(unpack("C*", substr($terrain, $offset, 256)) as $c) {
				$data["heightMap"][] = $c - $yoff;
			}
			$offset += 256;
			$data["biomeColors"] = [];
			foreach(unpack("N*", substr($terrain, $offset, 1024)) as $c){
				$data["biomeColors"][] = $c;
			}
			$offset += 1024;
		}
		return new BaseChunk($this,$data);
	}
	public function putChunk($cX,$cZ,Chunk $chunk) {
		die(__METHOD__.","__LINE__.": TODO\n");
	}
	public function newChunk(array &$data) {
		die(__METHOD__.","__LINE__.": TODO\n");
	}
}
