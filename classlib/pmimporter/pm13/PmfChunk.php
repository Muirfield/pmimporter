<?php
namespace pmimporter\pm13;
use pmimporter\Chunk;

class PmfChunk extends Chunk {
  protected $xPos;
  protected $zPos;
  protected $pmfLevel;
  /**
	 * $data is an array with the following elements:
	 *
	 * - int x
	 * - int z
	 * - PMFLevel $pmf-level
	 * @param $data - input to initialize chunk
	 */
	public function __construct(array &$data) {
    $this->xPos = isset($data["x"]) ? $data["x"] : 0;
    $this->zPos = isset($data["z"]) ? $data["z"] : 0;
    $this->pmfLevel = $data["pmf-level"];
  }
	static public function fromBinary($data, $yoff = 0) {

  }
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
