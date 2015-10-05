<?php
namespace pmimporter\anvil;

use pmimporter\generic\PcFormat;

class Anvil extends PcFormat {
	public static function getFormatName() {
		return "anvil";
	}
	public static function isValid($path) {
		if (!file_exists($path."/level.dat") || !is_dir($path."/region/")) return false;
		$files = glob($path."/region/*.mca");
		return $files !== false && count($files) != 0;
	}
	public static function getProviderOrder() {
		return self::ORDER_YZX;
	}
	public static function writeable() {
		return false;
	}
	public function __construct($path, $settings = null) {
		$this->initFormat(self::class, $path, $settings);
	}

	public function getChunk($cx,$cz) {
		$data = $this->readChunk($cx,$cz);
		return AnvilChunk::fromBinary($data);
	}
	protected function getFileExtension() {
		return "mca";
	}
}
