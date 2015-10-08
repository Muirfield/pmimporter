<?php
namespace pmimporter\generic;
use pmimporter\Chunk;
use pmimporter\LevelFormat;
use pmsrc\utils\Binary;

use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\Byte;
use pmsrc\nbt\tag\ByteArray;
use pmsrc\nbt\tag\Compound;
use pmsrc\nbt\tag\Enum;
use pmsrc\nbt\tag\Int;
use pmsrc\nbt\tag\IntArray;
use pmsrc\nbt\tag\Long;

class BaseChunk implements Chunk {
	protected $level;
	protected $xPos;
	protected $zPos;
	protected $blocks;
	protected $meta;
	protected $blockLight;
	protected $skyLight;
	protected $biomeColors;
	protected $heightMap;
	protected $entities;
	protected $tiles;
	protected $generated;
	protected $populated;
	protected $lighted;

	/**
	 * $data is an array with the following elements:
	 *
	 * - int x
	 * - int z
	 * - str[] blocks
	 * - str[] meta
	 * - str[] blockLight
	 * - str[] skyLight
	 * - int[] biomeColors
	 * - int[] heightMap
	 * - nbt[] entities
	 * - nbt[] tiles
	 * - bool isGenerated
	 * - bool isPopulated
	 * - bool isLightPopulated
	 * @param $data - input to initialize chunk
	 */
	public function __construct(LevelFormat $fmt,array &$data) {
		$this->level = $fmt;
		$this->xPos = isset($data["x"]) ? $data["x"] : 0;
		$this->zPos = isset($data["z"]) ? $data["z"] : 0;

		$this->blocks = isset($data["blocks"]) ? $data["blocks"] : str_repeat("\x00", 32768);
		$this->meta = isset($data["meta"]) ? $data["meta"] : str_repeat("\x00", 16384);
		$this->blockLight = isset($data["blockLight"]) ? $data["blockLight"] : str_repeat("\x00", 16384);
		$this->skyLight = isset($data["skyLight"]) ? $data["skyLight"] : str_repeat("\xff", 16384);
		$this->biomeColors = isset($data["biomeColors"]) ? $data["biomeColors"] : array_fill(0,256,Binary::readInt("\x00\x85\xb2\x4a"));

		$this->heightMap = isset($data["heightMap"]) ? $data["heightMap"] : array_fill(0,256,127);
		$this->entities =  isset($data["entities"]) ? $data["entities"] : [];
		$this->tiles =  isset($data["tiles"]) ? $data["tiles"] : [];
		$this->generated = isset($data["isGenerated"]) ? $data["isGenerated"] : true;
		$this->populated = isset($data["isPopulated"]) ? $data["isPopulated"] : true;
		$this->lighted = isset($data["isLightPopulated"]) ? $data["isLightPopulated"] : false;
	}
	public function getLevel() {
		return $this->level;
	}
	public function getBlocks() {
		return $this->blocks;
	}
	public function getRawBlocks() {
		return $this->blocks;
	}
	public function getMeta() {
		return $this->meta;
	}
	public function getRawMeta() {
		return $this->meta;
	}
	public function getBlockLight() {
		return $this->blockLight;
	}
	public function getRawBlockLight() {
		return $this->blockLight;
	}
	public function getSkyLight() {
		return $this->skyLight;
	}
	public function getRawSkyLight() {
		return $this->skyLight;
	}
	public function getBiomeColors() {
		return $this->biomeColors;
	}
	public function getHeightMap() {
		return $this->heightMap;
	}
	public function isGenerated() {
		return $this->generated;
	}
	public function isPopulated() {
		return $this->populated;
	}
	public function isLightPopulated() {
		return $this->lighted;
	}
	public function getX() {
		return $this->xPos;
	}
	public function getZ() {
		return $this->zPos;
	}
}
