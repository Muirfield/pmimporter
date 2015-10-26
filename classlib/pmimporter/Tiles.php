<?php
namespace pmimporter;
use pmimporter\Misc;
use pmsrc\nbt\tag\IntTag;

abstract class Tiles {
	const BAD_ITEM_ID = 1;
	protected static $tiles = [];

	public static function __init() {
		if (count(self::$tiles)) return; // Only read them once...
		if (defined('CLASSLIB_DIR')) {
			$tab = Misc::readTable(CLASSLIB_DIR."pmimporter/tiles.txt",1);
		} else {
			$tab = Misc::readTable(dirname(realpath(__FILE__))."/tiles.txt",1);
		}
		if ($tab === false) die("Unable to read tiles.txt\n");
		foreach ($tab as $ln)	 {
			$id = array_shift($ln);
			self::$tiles[$id] = $id;
		}
	}
	static public function convert($data,$xoff =0, $yoff = 0, $zoff =0) {
		$output = [];
		foreach ($data as $s) {
			$d = clone $s;
			if (!isset($d->id)) continue;
			if (!isset(self::$tiles[$d->id->getValue()])) continue;

      if ($xoff !== 0 && isset($d->x)) $d->x->setValue($d->x->getValue() + $xoff);
      if ($yoff !== 0 && isset($d->y)) {
        $y = $d->y->getValue() + $yoff;
        if ($y < 0 || $y > PM_MAX_HEIGHT) continue;
        $d->y->setValue($y);
      }
			if (isset($d->Items)) {
				foreach ($d->Items as $item) {
					if (Items::getItemById($item->id->getValue()) == null) {
						$item->id->setValue(self::BAD_ITEM_ID);
					}
				}
			}

      $output[] = $d;

		}
		return $output;
	}
}
