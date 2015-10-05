<?php
namespace pmimporter;
use pmimporter\Misc;


abstract class Entities {
	protected static $entityIds = [];
	protected static $entityNames = [];

	public static function __init() {
		if (count(self::$entityIds)) return; // Only read them once...
		if (defined('CLASSLIB_DIR')) {
			$tab = Misc::readTable(CLASSLIB_DIR."pmimporter/entities.txt");
		} else {
			$tab = Misc::readTable(dirname(realpath(__FILE__))."/entities.txt");
		}
		if ($tab === null) die("Unable to read entities.txt\n");
		foreach ($tab as $ln)	 {
			$code = array_shift($ln);
			$name = array_shift($ln);
			self::$entityIds[$name] = $code;
			self::$entityNames[$code] = $name;
		}
	}
	public static function getId($id) {
		if (isset(self::$entityIds[$id]) && self::$entityIds[$id] > 0) return $id;
		return null;
	}
	public static function getEntityId($name) {
		if (isset(self::$entityIds[$name]) && self::$entityIds[$name] > 0)
			return self::$entityIds[$name];
		return null;
	}
	public static function getEntityById($id) {
		if (isset(self::$entityNames[$id])) return self::$entityNames[$id];
		return null;
	}
}
