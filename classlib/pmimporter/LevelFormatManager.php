<?php
namespace pmimporter;
use pmimporter\LevelFormat;
use pmimporter\Chunk;

abstract class LevelFormatManager{
	protected static $formats = [];

	/**
	 * @param str $class
	 */
	public static function addFormat($class){
		if(!is_subclass_of($class, LevelFormat::class)) die("$class is not a subclass of LevelFormat\n");
		/** @var LevelProvider $class */
		self::$formats[strtolower($class::getFormatName())] = $class;
	}
	/**
	 * @param str $name
	 * @return str
	 */
	public static function getFormatByName($name) {
		$name = trim(strtolower($name));
		return isset(self::$formats[$name]) ? self::$formats[$name] : null;
	}
	/**
	 * Returns a LevelFormat class for this path, or null
	 *
	 * @param str $path
	 *
	 * @return str|null
	 */
	public static function getFormat($path){
		foreach(self::$formats as $format){
			if($format::isValid($path)){
				return $format;
			}
		}
		return null;
	}
	/**
	 * Import chunk between formats
	 */
	public static function importChunk(LevelFormat $dst,$x,$z,Chunk $chunk,$convert) {
		$data["x"] = $x; $adjX = $x - $chunk->getX();
		$data["z"] = $z; $adjZ = $z - $chunk->getZ();
		$data["biomeColors"] = $chunk->getBiomeColors();
		$data["heightMap"] = $chunk->getHeightMap();
		$data["isGenerated"] = $chunk->isGenerated();
		$data["isPopulated"] = $chunk->isPopulated();
		$data["isLightPopulated"] = $chunk->isLightPopulated();

		$src = $chunk->getLevel();
		if ($src::getProviderOrder() == $dst::getProviderOrder()) {
			// This is a straight copy!
			if ($convert) {
				$rawblocks = $chunk->getRawBlocks();
				if (is_array($rawblocks)) {
					$data["blocks"] = [];
					foreach ($rawblocks as $y => $sect) {
						$data["blocks"][$y] = strtr($sect,Blocks::$trTab);
					}
				} else {
					$data["blocks"] = strtr($rawblocks,Blocks::$trTab);
				}
			} else {
				$data["blocks"] = $chunk->getRawBlocks();
			}

			$data["meta"] = $chunk->getRawMeta();
			$data["blockLight"] = $chunk->getRawBlockLight();
			$data["skyLight"] = $chunk->getRawSkyLight();
		} else {
			// RE-ORDERING may be required...
			if ($convert) {
				$data["blocks"] = strtr($chunk->getBlocks(),Blocks::$trTab);
			} else {
				$data["blocks"] = $chunk->getBlocks();
			}
			$data["meta"] = $chunk->getMeta();
			$data["blockLight"] = $chunk->getBlockLight();
			$data["skyLight"] = $chunk->getSkyLight();
		}

		//$data["entities"] = ;
		//$data["tiles"] = ;
		$newChunk = $dst->newChunk($data);
		$dst->putChunk($x,$z,$newChunk);
	}

}
