<?php
namespace pmimporter\pm13;
use pmimporter\LevelFormat;
use pmimporter\LevelFormatManager;
use pocketmine_1_3\pmf\PMFLevel;
use pmsrc\math\Vector3;

class Pm13 implements LevelFormat {
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
	public static function writeable() {
		return false;
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
				$chunks[] = [ $x, $z];
			}
		}
		return $chunks;
	}
}
