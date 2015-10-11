<?php
namespace pmimporter;
use pmimporter\Misc;
use pmsrc\nbt\tag\IntTag;

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
	public static function getEntityById($id) {
		if (isset(self::$entityNames[$id])) return self::$entityNames[$id];
		return null;
	}
	static public function convert($data,$xoff =0, $yoff = 0, $zoff =0) {
		$output = [];
		foreach ($data as $s) {
			$d = clone $s;
			if (!isset($d->id)) continue;
			if (is_numeric($d->id->getValue())) {
				if (self::getEntityById($d->id->getValue()) === null) continue;
			} else {
				if (!isset(self::$entityIds[$d->id->getValue()])) continue;
				$nid = self::$entityIds[$d->id->getValue()];
				// Check if it is a Zombie or a Zombie Villager...
				if ($nid === 32 && isset($d->isVillager) && $d->isVillager->getValue()) $nid = 44;
				$d->id = new IntTag("id", $nid);
			}
			if ($d->id->getValue() == 64) {
				// Need to convert items!
				die("TODO: NEED TO CONVERT ITEMS\n");
			}
			if (isset($d->Pos) && count($d->Pos) == 3) {
				if ($xoff !== 0) $d->Pos[0]->setValue($d->Pos[0]->getValue() + $xoff);
				if ($yoff !== 0) {
					$y = $d->Pos[1]->getValue() + $yoff;
					if ($y < 0 || $y > PM_MAX_HEIGHT) continue;
					$d->Pos[1]->setValue($y);
				}
				if ($zoff !== 0) $d->Pos[3]->setValue($d->Pos[3]->getValue() + $zoff);
			}
			$output[] = $d;
		}
		return $output;
	}
	/*
	public static function getId($id) {
		if (isset(self::$entityIds[$id]) && self::$entityIds[$id] > 0) return $id;
		return null;
	}
	public static function getEntityId($name) {
		if (isset(self::$entityIds[$name]) && self::$entityIds[$name] > 0)
			return self::$entityIds[$name];
		return null;
	}*/
}
