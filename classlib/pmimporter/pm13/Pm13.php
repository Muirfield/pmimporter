<?php
namespace pmimporter\pm13;
use pmimporter\LevelFormat;
use pmimporter\LevelFormatManager;
use pmimporter\generic\ReadOnlyFormat;
use pocketmine_1_3\pmf\PMFLevel;
use pmsrc\math\Vector3;

use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\ByteTag;
use pmsrc\nbt\tag\ByteArrayTag;
use pmsrc\nbt\tag\CompoundTag;
use pmsrc\nbt\tag\EnumTag;
use pmsrc\nbt\tag\StringTag;
use pmsrc\nbt\tag\ShortTag;
use pmsrc\nbt\tag\IntTag;
use pmsrc\nbt\tag\IntArrayTag;
use pmsrc\nbt\tag\LongTag;

use pmimporter\generic\BaseChunk;


class Pm13 extends ReadOnlyFormat {
	protected $path;
	protected $pmfLevel;
	protected $tiles;

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

				$yml = dirname($this->pmfLevel->getFile())."/tiles.yml";
				$this->tiles = file_exists($yml) ? yaml_parse_file($yml) : [];
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

		$xoff = $cX << 4; $zoff = $cZ << 4;
		$data["blocks"] = "";
		$data["meta"] = "";
		for ($x =0; $x < 16 ; $x++) {
			for ($z = 0; $z < 16 ; $z++) {
				$data["blocks"] .= $this->pmfLevel->getBlockIdColumn($xoff+$x,$zoff+$z);
				$data["meta"] .= $this->pmfLevel->getBlockDamageColumn($xoff+$x,$zoff+$z);
			}
		}
		//echo "BLOCKS=".strlen($data["blocks"])."\n";
		$data["tiles"] = $this->getTileEntities($cX,$cZ,$yoff);
		if ($yoff < 0) {
			$yoff = -$yoff;
			$data["blocks"] = Shifter::down($data["blocks"],"\x00",$yoff,PM_HEIGHT_BITS);
			if (($yoff & 1) == 0) {
				$data["meta"] = Shifter::down($data["meta"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
			} else {
				$data["meta"] = Shifter::nibbleDown($data["meta"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
			}
		} elseif ($yoff > 0) {
			$data["blocks"] = Shifter::up($data["blocks"],"\x00",$yoff,PM_HEIGHT_BITS);
			if (($yoff & 1) == 0) {
				$data["meta"] = Shifter::up($data["meta"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
			} else {
				$data["meta"] = Shifter::nibbleUp($data["meta"],"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
			}
		}
		return $this->newChunk($data);
	}
	protected static function convertInventory($name,$src) {
		$items = [];
		if (isset($src)) {
			foreach ($src as $sl) {
				$items[] = new CompoundTag(false,[new ByteTag("Count",$sl["Count"]),
														new ByteTag("Slot",$sl["Slot"]),
														new ShortTag("id",$sl["id"]),
														new ShortTag("Damage",$sl["Damage"])]);
			}
		}
		$nbt = new EnumTag($name,$items);
		$nbt->setTagType(NBT::TAG_Compound);
		return $nbt;
	}
	public function getTileEntities($cX,$cZ,$yoff=0) {
		$tiles = [];

		$min_x = $cX << 4; $min_z = $cZ << 4;
		$max_x = $min_x+15; $max_z = $min_z+15;
		foreach ($this->tiles as $tile) {
			if (!isset($tile["id"])) continue;

			if (isset($tile["x"]) && isset($tile["y"]) && isset($tile["z"])) {
				if ($tile["x"] < $min_x || $tile["x"] > $max_x ||
					$tile["z"] < $min_z || $tile["z"] > $max_z) continue;
				if ($yoff != 0) {
					$tile["y"] += $yoff;
					if ($tile["y"] < 0 || $tile["y"] > PM_MAX_HEIGHT) continue;
				}
			} else {
				continue;
			}
			switch (strtolower($tile["id"])) {
				case "sign":
					$tiles[] =  new CompoundTag("",[new StringTag("id","sign"),
															new StringTag("Text1",$tile["Text1"]),
															new StringTag("Text2",$tile["Text2"]),
															new StringTag("Text3",$tile["Text3"]),
															new StringTag("Text4",$tile["Text4"]),
															new IntTag("x",$tile["x"]),
															new IntTag("y",$tile["y"]),
															new IntTag("z",$tile["z"])]);
					break;
				case "furnace":
					$tiles[] = new CompoundTag("",[new StringTag("id","furnace"),
														new ShortTag("BurnTime",$tile["BurnTime"]),
														new ShortTag("BurnTicks",$tile["BurnTicks"]),
														new ShortTag("CookTime",$tile["CookTime"]),
														new ShortTag("CookTimeTotal",$tile["MaxTime"]),
														self::convertInventory("Items",$tile["Items"]),
														new IntTag("x",$tile["x"]),
														new IntTag("y",$tile["y"]),
														new IntTag("z",$tile["z"])]);
					break;
				case "chest":
					$chest = [new StringTag("id","chest"),
								self::convertInventory("Items",$tile["Items"]),
								new IntTag("x",$tile["x"]),
								new IntTag("y",$tile["y"]),
								new IntTag("z",$tile["z"])];
					if (isset($tile["pairx"]))
						$chest[] = new IntTag("pairx",$tile["pairx"]);
					if (isset($tile["pairz"]))
						$chest[] = new IntTag("pairz",$tile["pairz"]);
					$tiles[] = new CompoundTag("",$chest);
					break;
				default:
					// Not supported tile Id
					continue;
			}
		}
		return $tiles;
	}
}
