<?php
namespace pmimporter\generic;
//use pmsrc\math\Vector3;
use pmimporter\Chunk;
use pmimporter\LevelFormat;

abstract class ReadOnlyFormat implements LevelFormat {
	public static function writeable() {
    return false;
  }
	public function putChunk($cX,$cZ,Chunk $chunk) {
    die ($this::getFormatName().": format does not support\n");
  }
	public function newChunk(array &$data) {
    die ("INTERNAL ERROR: ".__METHOD__."\n");
  }
}
