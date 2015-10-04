<?php
namespace pmimporter\mcregion;
use pmimporter\generic\PcChunk;
use pmimporter\RegionLoader;

use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\Byte;
use pmsrc\nbt\tag\ByteArray;
use pmsrc\nbt\tag\Compound;
use pmsrc\nbt\tag\Enum;
use pmsrc\nbt\tag\Int;
use pmsrc\nbt\tag\IntArray;
use pmsrc\nbt\tag\Long;

class McrChunk extends PcChunk {
	static public function fromBinary($binary, $yoff = 0) {
		$reader = new NBT(NBT::BIG_ENDIAN);
		$reader->readCompressed($binary, ZLIB_ENCODING_DEFLATE);
		$chunk = $reader->getData();
		if(!isset($chunk->Level) or !($chunk->Level instanceof Compound) return null;

		$nbt = $chunk->Level;
		$data = self::fromNBT($nbt);

		if(isset($nbt->Entities) && $nbt->Entities instanceof Enum){
			$nbt->Entities->setTagType(NBT::TAG_Compound);
			$data["entities"] = $nbt->Entities->getValue();
		}
		if(isset($nbt->TileEntities) && $nbt->TileEntities instanceof Enum){
			$nbt->TileEntities->setTagType(NBT::TAG_Compound);
			$data["tiles"] = $nbt->TileEntities->getValue();
		}

		if ($yoff == 0) {
			if (isset($nbt->Blocks)) $data["blocks"] = $nbt->Blocks->getValue();
			if (isset($nbt->Data)) $data["meta"] = $nbt->Data->getValue();
			if (isset($nbt->BlockLight)) $data["blockLight"] = $nbt->BlockLight->getValue();
			if (isset($nbt->SkyLight)) $data["skyLight"] = $nbt->SkyLight->getValue();
		} else {
			die("LOADING OFFSETS NOT IMPLEMENTED!\n");
		}
		return $data;
	}
	public function toBinary() {
		$nbt = $this->initNBT();
		if (count($this->entities) > 0) $nbt->Entities = self::addNBT("Entities",$this->entities);
		if (count($this->tiles) > 0) $nbt->TileEntities = self::addNBT("TileEntities",$this->tiles);

		if ($this->isGenerated()) {
			$nbt->Blocks = new ByteArray("Blocks",$this->blocks);
			$nbt->Data = new ByteArray("Data", $this->meta);
			$nbt->BlockLight = new ByteArray("BlockLight", $this->blockLight);
			$nbt->SkyLight = new ByteArray("SkyLight", $this->skyLight);
			$nbt->BiomeColors = new IntArray("BiomeColors", $this->biomeColors);
			$nbt->HeightMap = new IntArray("HeightMap", $this->heightMap);
		}

		$writer = new NBT(NBT::BIG_ENDIAN);
		$writer->setData(new Compound("", ["Level" => $nbt]));
		return $writer->writeCompressed(ZLIB_ENCODING_DEFLATE, RegionLoader::$COMPRESSION_LEVEL);
}
