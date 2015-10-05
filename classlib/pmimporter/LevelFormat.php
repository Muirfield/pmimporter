<?php
namespace pmimporter;
//use pmsrc\math\Vector3;

interface LevelFormat {
	const ORDER_YZX = 0;
	const ORDER_ZXY = 1;

	/**
	 * Returns the full provider name, like "anvil" or "mcregion", will be used
	 * to find the correct format.
	 *
	 * @return string
	 */
	public static function getFormatName();
	/**
	 * Tells if the path is a valid level.
	 * This must tell if the current format supports opening the files in the
	 * directory
	 *
	 * @param string $path
	 *
	 * @return true
	 */
	public static function isValid($path);

	/**
	 * @return int
	 */
	public static function getProviderOrder();
	/**
	 * @return  bool
	 */
	public static function writeable();
	/**
	 * Create a new LevelFormat object.  If $settings is null, the level
	 * is assumed exist already.  If a new level needs to be created, the
	 * $settings array should be filled with:
	 *
	 * - spawn (Vector3): spawn point
	 * - seed (int): seed for generator
	 * - generator (str): terrain generator name
	 * - presets (str): terrain generator preset options
	 *
	 * @param str $path
	 * @param mixed[]|null $settings
	 */
	public function __construct($path, $settings = null);

	/**
	 * Returns the generator name
	 *
	 * @return str
	 */
	public function getGenerator();
	/**
	 * @return str
	 */
	public function getPresets();
	/**
	 * @return int
	 */
	public function getSeed();
	/**
	 * @return Vector3
	 */
	public function getSpawn();
	/**
	 * Returns an array with [x,z] chunks
	 * @return array
	 */
	public function getChunks();
	/**
	 * @param int $cX
	 * @param int $cZ
	 * @param str $data
	 */
	public function writeChunk($cX,$cZ,$data);
	/**
	 * @param int $cX
	 * @param int $cZ
	 * @return str
	 */
	public function readChunk($cX,$cZ);
	/**
	 * @param int $cX
	 * @param int $cZ
	 * @param int $yoff
	 * @return Chunk
	 */
	public function getChunk($cX,$cZ,$yoff=0);

}
