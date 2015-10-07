<?php
namespace pmimporter\mcregion;

use pmimporter\generic\PcFormat;
use pmimporter\mcregion\McrChunk;
use pmimporter\Chunk;

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
		return true;
	}
	public function __construct($path, $settings = null) {
		//echo __METHOD__.",".__LINE__." - $path\n";//##DEBUG
		$this->initFormat(self::class, $path, $settings);
	}
	public function getChunk($cx,$cz,$yoff=0) {
		$data = $this->readChunk($cx,$cz);
    return McrChunk::fromBinary($this,$data,$yoff);
	}
	protected function getFileExtension() {
		return "mcr";
	}
	public function newChunk(array &$data) {
		return new McrChunk($this,$data);
	}
}
