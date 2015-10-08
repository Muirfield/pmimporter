<?php
namespace pmimporter\mcpe020;
use pmimporter\LevelFormat;
use pmimporter\generic\ReadOnlyFormat;
use pmimporter\LevelFormatManager;
use pmimporter\generic\BaseChunk;
use pmsrc\utils\Binary;
use pmsrc\math\Vector3;
use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\Double;
use pmsrc\nbt\tag\Int;


use pocketmine_1_3\PocketChunkParser;

class McPe020 extends ReadOnlyFormat {
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
		return "flat";
	}
	public function getPresets() {
		return "2;7,55x1,9x3,2;1;";
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
				$chunks[implode(",",[$x,$z])] = [ $x, $z];
			}
		}
		return $chunks;
	}

	public function getChunk($x,$z,$yoff=0) {
		$map = $this->chunks->getParsedChunk($x,$z);
		$data = [
			"x" => $x,
			"z" => $z,
		];
		if ($yoff == 0) {
			$data["blocks"] = implode("",$map[0]);
			$data["meta"] = implode("",$map[1]);
			$data["skyLight"] = implode("",$map[2]);
			$data["blockLight"] = implode("",$map[3]);
		} elseif ($yoff < 0) {
			$yoff = -$yoff;
			$data["blocks"] = Shifter::down(implode("",$map[0]),"\x00",$yoff,PM_HEIGHT_BITS);
			if (($yoff & 1) == 0) {
				$data["meta"] = Shifter::down(implode("",$map[1]),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				$data["skyLight"] = Shifter::down(implode("",$map[2]),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
				$data["blockLight"] = Shifter::down(implode("",$map[3]),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
			} else {
				$data["meta"] = Shifter::nibbleDown(implode("",$map[1]),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				$data["skyLight"] = Shifter::nibbleDown(implode("",$map[2]),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
				$data["blockLight"] = Shifter::nibbleDown(implode("",$map[3]),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
			}
		} else {
			$data["blocks"] = Shifter::up(implode("",$map[0]),"\x00",$yoff,PM_HEIGHT_BITS);
			if (($yoff & 1) == 0) {
				$data["meta"] = Shifter::up(implode("",$map[1]),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				$data["skyLight"] = Shifter::up(implode("",$map[2]),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
				$data["blockLight"] = Shifter::up(implode("",$map[3]),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
			} else {
				$data["meta"] = Shifter::nibbleUp(implode("",$map[1]),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				$data["skyLight"] = Shifter::nibbleUp(implode("",$map[2]),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
				$data["blockLight"] = Shifter::nibbleUp(implode("",$map[3]),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
			}
		}
		$min_x = $x << 4; $max_x = ($x << 4) + 15;
		$min_z = $z << 4; $max_z = ($z << 4) + 15;


		if (isset($this->nbt["Entities"])) {
			$data["entities"] = [];
			foreach ($this->nbt["Entities"]->getValue() as $n) {
				if (!isset($n->Pos) || !isset($n->id) || count($n->Pos) !=3) continue;
				list($x,$y,$z) = $n->Pos;
				if ($x < $min_x || $x > $max_x || $z < $min_z || $z > $max_z) continue;
				$cc = clone $n;
				if ($yoff != 0) {
					$y -= $yoff;
					if ($y < 0 || $y > PM_MAX_HEIGHT) continue;
					$cc->Pos[1] = new Double("",$y);
				}
				$data["entities"][] = $cc;
			}
		}

		if (isset($this->nbt["TileEntities"])) {
			$data["tiles"] = [];
			foreach ($this->nbt["TileEntities"]->getValue() as $n) {
				if (isset($n->x) && isset($n->y) && isset($n->z)) {
					if ($n->x->getValue() < $min_x || $n->x->getValue() > $max_x ||
						$n->z->getValue() < $min_z || $n->z->getValue() > $max_z)
						continue;
					$cc = clone $n;
					if ($yoff != 0) {
						$y = $cc->y->getValue() - $yoff;
						if ($y < 0 || $y > PM_MAX_HEIGHT) continue;
						$cc->y->setValue($y);
					}
					$data["tiles"][] = $cc;
				}
			}
		}
		return new BaseChunk($this,$data);
	}

}
