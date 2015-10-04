<?php
namespace pmimporter;
use pmimporter\LevelFormat;

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
}
