<?php
namespace pmimporter;

interface Chunk {
	/**
	 * $data is an array with the following elements:
	 *
	 * - int xPos
	 * - int zPos
	 * - str[] blocks
	 * - str[] meta
	 * - str[] blockLight (optional)
	 * - str[] skyLight (optional)
	 * - int[] biomeColors (optional)
	 * - int[] heightMap (optional)
	 * - nbt[] entities (optional)
	 * - nbt[] tiles (optional)
	 * - bool isGenerated (optional)
	 * - bool isPopulated (optional)
	 * - bool isLightPopulated (optional)
	 * @param $data - input to initialize chunk
	 */
	public function __construct(array &$data);
	static public function fromBinary($data, $yoff = 0);
	public function toBinary();
	public function getBlocks();
	public function getRawBlocks();
	public function getMeta();
	public function getRawMeta();
	public function getBlockLight();
	public function getRawBlockLight();
	public function getSkyLight();
	public function getRawSkyLight();
	public function getBiomeColors();
	public function getHeightMap();
	public function isGenerated();
	public function isPopulated();
	public function isLightPopulated();
	public function getX();
	public function getZ();
}
