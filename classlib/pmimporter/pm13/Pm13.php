<?php
namespace pmimporter\pm13;
use pmimporter\LevelFormat;
use pmimporter\LevelFormatManager;
use pmimporter\generic\ReadOnlyFormat;
use pocketmine_1_3\pmf\PMFLevel;
use pmsrc\math\Vector3;

use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\Byte;
use pmsrc\nbt\tag\ByteArray;
use pmsrc\nbt\tag\Compound;
use pmsrc\nbt\tag\Enum;
use pmsrc\nbt\tag\Int;
use pmsrc\nbt\tag\IntArray;
use pmsrc\nbt\tag\Long;

use pmimporter\generic\BaseChunk;


class Pm13 extends ReadOnlyFormat {
	protected $path;
	protected $pmfLevel;

	public static function getFormatName() { return "PMF_1.3"; }
	public static function isValid($path) {
		return (file_exists($path."/level.pmf")
				  && file_exists($path."/entities.yml")
				  && file_exists($path."/tiles.yml")
				  && is_dir($path."/chunks"));
	}
	public static function getProviderOrder() {
		return self::ORDER_ZXY;
	}
	public function __construct($path,$settings=null) {
		if (file_exists($path)) {
			if (!is_dir($path)) die("$path: Already exists!\n");
			$fmt = LevelFormatManager::getFormat($path);
			if ($fmt !== null) {
				if ($fmt != self::class) die("$path: format mismatch\n");
				$this->path = $path;
				$pmfLevel = new PMFLevel($path."/level.pmf");
				$this->pmfLevel = $pmfLevel;
				return;
			}
			die("$path: Invalid format\n");
		} else {
			die("$path: does not exist\n");
		}
	}
	public function getGenerator() {
		return "flat";
	}
	public function getPresets() {
		return "2;7,55x1,9x3,2;1;";
	}
	public function getSeed() {
		return $this->pmfLevel->getAttr("seed");
	}
	public function getSpawn() {
		return new Vector3((float)$this->pmfLevel->getAttr("spawnX"),
												(float)$this->pmfLevel->getAttr("spawnY"),
												(float)$this->pmfLevel->getAttr("spawnZ"));
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
	public function getChunk($cX,$cZ,$yoff=0) {
		$this->pmfLevel->loadChunk($cX,$cZ);
		$data = [
			"x" => $cX,
			"z" => $cZ,
			"blocks" => "",
			"meta" => "",
		];
		// This is the simple (un-optimized) implementation

		$xoff = $cX << 4; $zoff = $cZ << 4;
		for ($x =0; $x < 16 ; $x++) {
			for ($z = 0; $z < 16 ; $z++) {
				if ($yoff > 0) {
					$data["blocks"] .= str_repeat("\x00",$yoff);
					$data["meta"] .= str_repeat("\x00",$yoff>>1);
					if (($yoff & 0x1) == 0x1) {
						$data["blocks"] .= "\x00";
						$data["meta"] = chr($this->pmfLevel->getBlockDamage($x,1,$z) << 4);
						++$yoff;
					}
				}
				$max = $yoff < 0 ? PM_MAX_HEIGHT + $yoff : PM_MAX_HEIGHT;
				for ($y = $yoff > 0 ? $yoff : 0; $y < $max; $y++ ) {
					$data["blocks"] .= chr($this->pmfLevel->getBlockID($x,$y-$yoff,$z));
					if (($y & 0x1) == 1) continue;
					$dl = $this->pmfLevel->getBlockDamage($x,$y-$yoff,$z);
					$dh = $this->pmfLevel->getBlockDamage($x,$y-$yoff+1,$z);
					$data["meta"] .= chr($dl | ($dh << 4));
				}
				if ($yoff < 0) {
					$data["blocks"] .= str_repeat("\x00",$yoff);
					$data["meta"] .= str_repeat("\x00",$yoff>>1);
				}
			}
		}
		$data["tiles"] = $this->getTileEntities($cX,$cZ,$yoff);
		return new BaseChunk($this,$data);
	}
	protected static function convertInventory($name,$src) {
		$items = [];
		if (isset($src)) {
			foreach ($src as $sl) {
				$items[] = new Compound(false,[new Byte("Count",$sl["Count"]),
														new Byte("Slot",$sl["Slot"]),
														new Short("id",$sl["id"]),
														new Short("Damage",$sl["Damage"])]);
			}
		}
		$nbt = new Enum($name,$items);
		$nbt->setTagType(NBT::TAG_Compound);
		return $nbt;
	}
	public function getTileEntities($cX,$cZ,$yoff=0) {
		$tiles = [];
		$yml = dirname($this->pmfLevel->getFile())."/tiles.yml";
		if (!file_exists($yml)) return $tiles;
		$yml = yaml_parse_file($yml);
		$min_x = $cX << 4; $min_z = $cZ << 4;
		$max_x = $min_x+15; $max_z = $min_z+15;
		foreach ($yml as $tile) {
			if (!isset($tile["id"])) continue;
			if (isset($tile["x"]) && isset($tile["y"]) && isset($tile["z"])) {
				if ($tile["x"] < $min_x || $tile["x"] > $max_x ||
					$tile["z"] < $min_z || $tile["z"] > $max_z) continue;
				if ($yoff != 0) {
					$tile["y"] -= $yoff;
					if ($tile["y"] < 0 || $tile["y"] > PM_MAX_HEIGHT) continue;
				}
			} else {
				continue;
			}
			switch ($tile["id"]) {
				case "sign":
					$tiles[] =  new Compound("",[new String("id","sign"),
															new String("Text1",$tile["Text1"]),
															new String("Text2",$tile["Text2"]),
															new String("Text3",$tile["Text3"]),
															new String("Text4",$tile["Text4"]),
															new Int("x",$tile["x"]),
															new Int("y",$tile["y"]),
															new Int("z",$tile["z"])]);
					break;
				case "furnace":
					$tiles[] = new Compound("",[new String("id","furnace"),
														new Short("BurnTime",$tile["BurnTime"]),
														new Short("BurnTicks",$tile["BurnTicks"]),
														new Short("CookTime",$tile["CookTime"]),
														new Short("CookTimeTotal",$tile["MaxTime"]),
														self::convertInventory("Items",$tile["Items"]),
														new Int("x",$tile["x"]),
														new Int("y",$tile["y"]),
														new Int("z",$tile["z"])]);
					break;
				case "chest":
					$chest = [new String("id","chest"),
								self::convertInventory("Items",$tile["Items"]),
								new Int("x",$tile["x"]),
								new Int("y",$tile["y"]),
								new Int("z",$tile["z"])];
					if (isset($tile["pairx"]))
						$chest[] = new Int("pairx",$tile["pairx"]);
					if (isset($tile["pairz"]))
						$chest[] = new Int("pairz",$tile["pairz"]);
					$tiles[] = new Compound("",$chest);
					break;
				default:
					// Not supported tile Id
					continue;
			}
		}
		return $tiles;
	}
}
