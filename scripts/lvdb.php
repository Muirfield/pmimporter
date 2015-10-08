<?php
if (!defined('CLASSLIB_DIR'))
	require_once(dirname(realpath(__FILE__)).'/../classlib/autoload.php');

use pmsrc\utils\Binary;
use pmsrc\utils\Utils;

define('CMD',array_shift($argv));
$file=array_shift($argv);
if (!isset($file)) die("No file specified\n");
if (!is_dir($file)) die("$file: does not exist\n");

$db = new \LevelDB($file,[
  "compression" => LEVELDB_ZLIB_COMPRESSION
]);
$it = $db->getIterator();
// Or loop in foreach
while ($it->valid()) {
  $key = $it->key(); $it->next();
  echo "KEY: ".Utils::hexdump($key);
  echo "VAL: ".Utils::hexdump($db->get($key));
}
$db->close();
