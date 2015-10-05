<?php
namespace pmimporter\mcpe020;

use pmimporter\generic\BaseChunk;
//use pocketmine\utils\Binary;
//use pmimporter\ImporterException;
//use pmimporter\Entities;
//use pocketmine\nbt\tag\String;
//use pocketmine\nbt\tag\Int;
//use pocketmine\nbt\tag\Enum;
//use pocketmine\nbt\tag\Double;


class McPe020Chunk extends BaseChunk {
  static public function fromBinary($data, $yoff = 0) {
    die(__METHOD__.": NOT IMPLEMENTED\n");
  }
  public function toBinary() {
    die(__METHOD__.": NOT IMPLEMENTED\n");
  }
  public function getBlocks() {
    return implode("",$this->blocks);
	}
	public function getMeta() {
    return implode("",$this->meta);
	}
	public function getBlockLight() {
    return implode("",$this->blockLight);
	}
	public function getSkyLight() {
    return implode("",$this->skyLight);
	}
}
