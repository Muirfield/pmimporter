<?php
namespace pmimporter;
use pmimporter\Utils;

abstract class Blocks {
	protected static $blockIds = [];
	protected static $blockNames = [];
	public static $trTab = [];

	const INVALID_BLOCK = 248;

	public static function __init() {
		if (count(self::$blockIds)) return; // Only read them once...

		// Read block definitions
		if (defined('CLASSLIB_DIR')) {
			$fp = fopen(CLASSLIB_DIR."pmimporter/blocks.txt","r");
		} else {
			$fp = fopen(dirname(realpath(__FILE__))."/blocks.txt","r");
		}
		if ($fp) {
			while (($ln = fgets($fp)) !== false) {
				if (preg_match('/^\s*[#;]/',$ln)) continue; // Skip comments
				$ln = preg_replace('/^\s+/','',$ln);
				$ln = preg_replace('/\s+$/','',$ln);
				if ($ln == '') continue;	// Skip empty lines
				$ln = preg_split('/\s+/',$ln);
				if ($ln < 2) continue;
				$code = array_shift($ln);
				$name = array_shift($ln);

				self::$blockNames[$code] = $name;
				self::$blockIds[$name] = $code;

				if ($code >= 0) {
					$cname = strtoupper(Utils::from_camel_case($name));
					define("BL_".$cname,$code);
				} else {
					self::$trTab[chr(-$code)] = isset($ln[0]) ? chr($ln[0]) : chr(self::INVALID_BLOCK);
				}
			}
			fclose($fp);
		} else {
			die("Unable to read blocks.txt\n");
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
	public static function addRule($cid,$nid) {
		if ($cid === null || $nid === null) return;
		if ($cid == $nid) return;
		if ($cid < 0) $cid = -$cid;
		if ($nid < 0) return;
		self::$blockConv[chr($cid)] = chr($nid);
	}
}
