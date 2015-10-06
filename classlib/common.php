<?php
//
// Initialize supported formats...
//
use pmimporter\LevelFormatManager;
use pmimporter\anvil\Anvil;
use pmimporter\mcpe020\McPe020;
use pmimporter\pm13\Pm13;
use pmimporter\mcregion\McRegion;
use pmimporter\leveldb\LevelDB;

//LevelFormatManager::addFormat(Anvil::class);
LevelFormatManager::addFormat(McRegion::class);
//LevelFormatManager::addFormat(McPe020::class);
//LevelFormatManager::addFormat(Pm13::class);
//if (extension_loaded("leveldb")) LevelFormatManager::addFormat(LevelDB::class);
