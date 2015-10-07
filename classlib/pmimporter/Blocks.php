<?php
namespace pmimporter;
use pmimporter\Misc;

abstract class Blocks {
	protected static $blockIds = [];
	protected static $blockNames = [];
	public static $trTab = [];

	const INVALID_BLOCK = 248;

	public static function __init() {
		if (count(self::$blockIds)) return; // Only read them once...

		// Read block definitions
		if (defined('CLASSLIB_DIR')) {
			$tab = Misc::readTable(CLASSLIB_DIR."pmimporter/blocks.txt");
		} else {
			$tab = Misc::readTable(dirname(realpath(__FILE__))."/blocks.txt");
		}
		if ($tab === false) die("Unable to read blocks.txt\n");

		for($i=0;$i<256;++$i) {
			self::$trTab[chr($i)] = chr(self::INVALID_BLOCK);
		}

		foreach ($tab as $ln)	 {
			$code = array_shift($ln);
			$name = array_shift($ln);
			$acode = $code < 0 ? -$code : $code;
			self::$blockNames[$acode] = $name;
			self::$blockIds[$name] = $acode;
			$chr = chr($acode);
			if ($code >= 0) {
				if (isset(self::$trTab[$chr])) unset(self::$trTab[$chr]);
			} else {
				self::$blockNames[$acode] .= " *";
			}
			if (count($ln)) self::$trTab[$chr] = chr((int)$ln[0]);
		}
	}
	public static function getBlockById($id) {
		if (isset(self::$blockNames[$id])) return self::$blockNames[$id];
		return null;
	}
	public static function getBlockByName($name) {
		if (isset(self::$blockIds[$name])) return self::$blockIds[$name];
		return null;
	}
	private static function getCode($id) {
		if ($id == null) return $id;
		if (is_numeric($id)) {
			if ($id < 0 || $id > 255) return null;
		 	return $id;
		}
		return self::getBlockByName($id);
	}
	public static function addRule($cid,$nid) {
		if (($cid = self::getCode($cid)) === null) return;
		if (($nid = self::getCode($nid)) === null) return;
		if ($cid == $nid) return;
		self::$trTab[chr($cid)] = chr($nid);
	}
}
