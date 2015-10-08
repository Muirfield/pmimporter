<?php
namespace pmimporter\mcregion;
use pmimporter\generic\PcChunk;
use pmimporter\RegionLoader;
use pmimporter\LevelFormat;
use pmimporter\Chunk;
use pmimporter\Blocks;
use pmimporter\Shifter;

use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\Byte;
use pmsrc\nbt\tag\ByteArray;
use pmsrc\nbt\tag\Compound;
use pmsrc\nbt\tag\Enum;
use pmsrc\nbt\tag\Int;
use pmsrc\nbt\tag\IntArray;
use pmsrc\nbt\tag\Long;

class McrChunk extends PcChunk {

	static public function fromBinary(LevelFormat $level,&$binary, $yoff = 0) {
		//echo "CHUNK: ".strlen($binary)." bytes\n";//##DEBUG

		$reader = new NBT(NBT::BIG_ENDIAN);
		$reader->readCompressed($binary, ZLIB_ENCODING_DEFLATE);
		$chunk = $reader->getData();
		if(!isset($chunk->Level) or !($chunk->Level instanceof Compound)) return null;

		$nbt = $chunk->Level;
		$data = self::fromNBT($nbt);

		if(isset($nbt->Entities) && ($nbt->Entities instanceof Enum)) {
			$nbt->Entities->setTagType(NBT::TAG_Compound);
			$data["entities"] = $nbt->Entities->getValue();
		}
		if(isset($nbt->TileEntities) && ($nbt->TileEntities instanceof Enum)) {
			$nbt->TileEntities->setTagType(NBT::TAG_Compound);
			$data["tiles"] = $nbt->TileEntities->getValue();
		}

		if ($yoff == 0) {
			if (isset($nbt->Blocks)) $data["blocks"] = $nbt->Blocks->getValue();
			if (isset($nbt->Data)) $data["meta"] = $nbt->Data->getValue();
			if (isset($nbt->BlockLight)) $data["blockLight"] = $nbt->BlockLight->getValue();
			if (isset($nbt->SkyLight)) $data["skyLight"] = $nbt->SkyLight->getValue();
		} elseif ($yoff < 0) {
			$yoff = -$yoff;
			if (isset($nbt->Blocks)) $data["blocks"] = Shifter::down($nbt->Blocks->getValue(),"\x00",$yoff,PM_HEIGHT_BITS);
			if (($yoff & 1) == 0) {
				if (isset($nbt->Data)) $data["meta"] = Shifter::down($nbt->Data->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->BlockLight)) $data["blockLight"] = Shifter::down($nbt->BlockLight->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->SkyLight)) $data["skyLight"] = Shifter::down($nbt->SkyLight->getValue(),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
			} else {
				if (isset($nbt->Data)) $data["meta"] = Shifter::nibbleDown($nbt->Data->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->BlockLight)) $data["blockLight"] = Shifter::nibbleDown($nbt->BlockLight->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->SkyLight)) $data["skyLight"] = Shifter::nibbleDown($nbt->SkyLight->getValue(),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
			}
		} else {
			if (isset($nbt->Blocks)) $data["blocks"] = Shifter::up($nbt->Blocks->getValue(),"\x00",$yoff,PM_HEIGHT_BITS);
			if (($yoff & 1) == 0) {
				if (isset($nbt->Data)) $data["meta"] = Shifter::up($nbt->Data->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->BlockLight)) $data["blockLight"] = Shifter::up($nbt->BlockLight->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->SkyLight)) $data["skyLight"] = Shifter::up($nbt->SkyLight->getValue(),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
			} else {
				if (isset($nbt->Data)) $data["meta"] = Shifter::nibbleUp($nbt->Data->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->BlockLight)) $data["blockLight"] = Shifter::nibbleUp($nbt->BlockLight->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->SkyLight)) $data["skyLight"] = Shifter::nibbleUp($nbt->SkyLight->getValue(),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
			}
		}
		return new McrChunk($level,$data);
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
}
