<?php
namespace pmimporter\mcpe020;
use pmimporter\LevelFormat;
use pmimporter\LevelFormatManager;
use pmimporter\mcpe020\McPe02Chunk;
use pmsrc\utils\Binary;
use pmsrc\math\Vector3;
use pmsrc\nbt\NBT;
use pocketmine_1_3\PocketChunkParser;

class McPe020 implements LevelFormat {
	protected $path;
	protected $levelData;
	protected $chunks;
	protected $nbt;

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

				$this->chunks = new PocketChunkParser();
				$this->chunks->loadFile($path.'/chunks.dat');
				$this->chunks->loadMap();
				$this->nbt = [];
				if (file_exists($path.'/entities.dat')) {
					$dat = file_get_contents($path.'/entities.dat');
					if (substr($dat,0,3) == "ENT" &&  ord(substr($dat,3,1)) == 0 &&
						 Binary::readLInt(substr($dat,4,4)) == 1 &&
						 Binary::readLInt(substr($dat,8,4)) == (strlen($dat) - 12)) {
						$nbt = new NBT(NBT::LITTLE_ENDIAN);
						$nbt->read(substr($dat,12));
						$this->nbt = $nbt->getData();
					}
				}
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
	public function writeChunk($cX,$cZ,$data) {
		die(__METHOD__.": NOT IMPLEMENTED\n");
	}
	public function readChunk($cX,$cZ) {
	die(__METHOD__.": NOT IMPLEMENTED\n");
	}
	public function getChunk($cX,$cZ,$yoff=0) {
		if ($yoff) die(__METHOD__.": YOFF!=0 NOT IMPLEMENTED\n");
		$map = $this->chunks->getParsedChunk($cX,$cZ);
		$data = [
			"x" => $x,
			"z" => $z,
			"blocks" => $map[$x][$z][0],
			"meta" => $map[$x][$z][1],
			"skyLight" => $map[$x][$z][2],
			"blockLight" => $map[$x][$z][3],
		];

		$min_x = $x << 4; $max_x = ($x << 4) + 15;
		$min_z = $z << 4; $max_z = ($z << 4) + 15;

		if (isset($this->nbt["Entities"])) {
			$data["entities"] = [];
			foreach ($this->nbt["Entities"]->getValue() as $n) {
				if (!isset($n->Pos) || !isset($n->id) || count($ent->Pos) !=3) continue;
				list($x,$y,$z) = $n->Pos;
				if ($x < $min_x || $x > $max_x || $z < $min_z || $z > $max_z) continue;
				$cc = clone $n;
				$data[$a][] = $cc;
			}
		}

		if (isset($this->nbt["TileEntities"])) {
			$data["tiles"] = [];
			foreach ($this->nbt["TileEntities"]->getValue() as $n) {
				if (isset($n->x) && isset($n->y) && isset($n->z)) {
					if ($n->x->getValue() < $min_x || $n->x->getValue() > $max_x ||
						$n->z->getValue() < $min_z || $n->z->getValue() > $max_z)
						continue;
					$tiles[] = clone $n;
				}
			}

		}
		return new McPe020Chunk($data);
	}
}
