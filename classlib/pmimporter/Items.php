<?php
namespace pmimporter;
use pmimporter\Misc;

abstract class Items {
	protected static $itemIds = [];
	protected static $itemNames = [];

	public static function __init() {
		if (count(self::$itemIds)) return; // Only read them once...
		if (defined('CLASSLIB_DIR')) {
			$tab = Misc::readTable(CLASSLIB_DIR."pmimporter/items.txt");
		} else {
			$tab = Misc::readTable(dirname(realpath(__FILE__))."/items.txt");
		}
		if ($tab === null) die("Unable to read items.txt\n");
		foreach ($tab as $ln)	 {
			$code = array_shift($ln);
			$name = array_shift($ln);
			self::$itemIds[$name] = $code;
			self::$itemNames[$code] = $name;
		}
	}
	public static function getItemById($id) {
		if (isset(self::$itemNames[$id])) return self::$itemNames[$id];
		return null;
	}
}
