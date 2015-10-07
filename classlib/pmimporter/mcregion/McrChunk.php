<?php
namespace pmimporter\mcregion;
use pmimporter\generic\PcChunk;
use pmimporter\RegionLoader;
use pmimporter\LevelFormat;
use pmimporter\Chunk;
use pmimporter\Blocks;

use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\Byte;
use pmsrc\nbt\tag\ByteArray;
use pmsrc\nbt\tag\Compound;
use pmsrc\nbt\tag\Enum;
use pmsrc\nbt\tag\Int;
use pmsrc\nbt\tag\IntArray;
use pmsrc\nbt\tag\Long;

class McrChunk extends PcChunk {
	static protected function genericShiftDown($input,$pad,$off,$bits) {
		$output = "";
		$pad = str_repeat($pad,$off);
		$len = (1<<$bits) - $off;
		$xbits = $bits+4;
		$zbits = $bits;
		for ($ox=0; $ox < 16; $ox++) {
			for ($oz=0; $oz < 16; $oz++) {
				$output .= substr($input,($ox << $xbits) | ($oz << $zbits) | $off,$len).$pad;
			}
		}
		return $output;
	}
	static protected function genericShiftUp($input,$pad,$off,$bits) {
		$output = "";
		$pad = str_repeat($pad,$off);
		$len = (1<<$bits) - $off;
		$xbits = $bits+4;
		$zbits = $bits;
		for ($ox=0; $ox < 16; $ox++) {
			for ($oz=0; $oz < 16; $oz++) {
				$output .= $pad.substr($input,($ox << $xbits) | ($oz << $zbits),$len).$pad;
			}
		}
		return $output;
	}
	static protected function nibbledShiftDown($input,$pad0,$off,$bits) {
		$output = "";
		$pad = str_repeat($pad0,$off);
		$len = (1<<$bits) - $off;
		$xbits = $bits+4;
		$zbits = $bits;
		for ($ox=0; $ox < 16; $ox++) {
			for ($oz=0; $oz < 16; $oz++) {
				$index = ($ox<<10)|($oz<<6)|$off;
				for ($oy = $off; $oy < $len ; $oy++) {
					$output .= chr((ord($input{$index++}) >> 4) | ((ord($input{$index}) & 0xf)<<4));
				}
				$output .=  chr((ord($input{$index}) >> 4) | ((ord($pad0) & 0xf)<<4));
				$output .= $pad;
			}
		}
		return $output;
	}
	static protected function nibbledShiftUp($input,$pad0,$off,$bits) {
		$output = "";
		$pad = str_repeat($pad0,$off);
		$len = (1<<$bits) - $off;
		$xbits = $bits+4;
		$zbits = $bits;
		//echo "off=$off bits=$bits len=$len xbits=$xbits zbits=$zbits\n";//##DEBUG
		for ($ox=0; $ox < 16; $ox++) {
			for ($oz=0; $oz < 16; $oz++) {
				$output .= $pad;
				$index = ($ox<<10)|($oz<<6);
				$output .= chr(((ord($input{$index++}) & 0xf) << 4) | (ord($pad0) & 0xf));;
				for ($oy = 1; $oy < $len ; $oy++) {
					$output .= chr((ord($input{$index++}) >> 4) | ((ord($input{$index}) & 0xf)<<4));
					//echo "ox=$ox oz=$oz oy=$oy len=$len ".strlen($output)."\n";
				}
			}
		}
		return $output;
	}
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
			if (isset($nbt->Blocks)) $data["blocks"] = self::genericShiftDown($nbt->Blocks->getValue(),"\x00",$yoff,PM_HEIGHT_BITS);
			if (($yoff & 1) == 0) {
				if (isset($nbt->Data)) $data["meta"] = self::genericShiftDown($nbt->Data->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->BlockLight)) $data["blockLight"] = self::genericShiftDown($nbt->BlockLight->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->SkyLight)) $data["skyLight"] = self::genericShiftDown($nbt->SkyLight->getValue(),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
			} else {
				if (isset($nbt->Data)) $data["meta"] = self::nibbledShiftDown($nbt->Data->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->BlockLight)) $data["blockLight"] = self::nibbledShiftDown($nbt->BlockLight->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->SkyLight)) $data["skyLight"] = self::nibbledShiftDown($nbt->SkyLight->getValue(),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
			}
		} else {
			if (isset($nbt->Blocks)) $data["blocks"] = self::genericShiftUp($nbt->Blocks->getValue(),"\x00",$yoff,PM_HEIGHT_BITS);
			if (($yoff & 1) == 0) {
				if (isset($nbt->Data)) $data["meta"] = self::genericShiftUp($nbt->Data->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->BlockLight)) $data["blockLight"] = self::genericShiftUp($nbt->BlockLight->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->SkyLight)) $data["skyLight"] = self::genericShiftUp($nbt->SkyLight->getValue(),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
			} else {
				if (isset($nbt->Data)) $data["meta"] = self::nibbledShiftUp($nbt->Data->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->BlockLight)) $data["blockLight"] = self::nibbledShiftUp($nbt->BlockLight->getValue(),"\x00",$yoff>>1,PM_HEIGHT_BITS-1);
				if (isset($nbt->SkyLight)) $data["skyLight"] = self::nibbledShiftUp($nbt->SkyLight->getValue(),"\xff",$yoff>>1,PM_HEIGHT_BITS-1);
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
