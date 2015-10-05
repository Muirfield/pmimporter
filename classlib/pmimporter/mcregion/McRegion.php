<?php
namespace pmimporter\mcregion;

use pmimporter\generic\PcFormat;
use pmimporter\mcregion\McrChunk;
//use pmimporter\generic\BaseFormat;


class McRegion extends PcFormat {
	protected $path;
	protected $levelData;

	public static function getFormatName() {
		return "mcregion";
	}
	public static function isValid($path) {
		if (!file_exists($path."/level.dat") || !is_dir($path."/region/")) return false;
		$files = glob($path."/region/*.mca");
		if ($files === false || count($files)) return false; // If Anvil files are present...
		$files = glob($path."/region/*.mcr");
		return $files !== false && count($files) != 0;
	}
	public static function getProviderOrder() {
		return self::ORDER_ZXY;
	}
	public static function writeable() {
		return false;
	}
	public function __construct($path, $settings = null) {
		$this->initFormat(self::class, $path, $settings);
	}
	public function getChunk($cx,$cz) {
		$data = $this->readChunk($cx,$cz);
    return McrChunk::fromBinary($data);
	}
	protected function getFileExtension() {
		return "mcr";
	}
}
