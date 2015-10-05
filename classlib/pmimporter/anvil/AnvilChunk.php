<?php
namespace pmimporter\anvil;
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

class AnvilChunk extends PcChunk {
	static public function fromBinary($data, $yoff = 0) {
		$reader = new NBT(NBT::BIG_ENDIAN);
		$reader->readCompressed($binary, ZLIB_ENCODING_DEFLATE);
		$chunk = $reader->getData();
		if(!isset($chunk->Level) or !($chunk->Level instanceof Compound)) return null;

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

		$data["blocks"] = [];
		$data["meta"] = [];
		$data["blockLight"] = [];
		$data["skyLight"] = [];

		if ($yoff == 0) {
			if (isset($nbt->Sections) && ($nbt->Sections instanceof Enum)) {
				foreach ($this->nbt->Sections as $section) {
					if (!($section instanceof Compound)) continue;
					$y = (int)$section["Y"];
					$data["blocks"][$y] = (string)$nbt["Blocks"];
					$data["meta"][$y] =  (string)$nbt["Data"];
					$data["blockLight"][$y] =  (string)$nbt["BlockLight"];
					$data["skyLight"][$y] =  (string)$nbt["SkyLight"];
				}
			}
		} else {
			die("LOADING OFFSETS NOT IMPLEMENTED!\n");
		}
	}
	public function __construct(array &$data) {
		if (isset($data["blocks"]) && !is_array($data["blocks"])) {
			// Convert from ZXY to YZX...
			die("THIS IS STILL TO BE IMPLEMENTED\n");
		}
		parent::__construct($data);
	}

	public function toBinary() {
		$nbt = $this->initNBT();
		if (count($this->entities) > 0) $nbt->Entities = self::addNBT("Entities",$this->entities);
		if (count($this->tiles) > 0) $nbt->TileEntities = self::addNBT("TileEntities",$this->tiles);

		if ($this->isGenerated() && count($this->blocks) > 0) {
			// Create sections...
			$ntb->Sections = new Enum("Sections",[]);
			$nbt->Sections->setTagType(NBT::TAG_Compound);
			foreach ($this->blocks as $y => $blocks) {
				$nbt->Sections[$y] = new Compound(null,[
					"Y" => new Byte("Y", $y),
					"Blocks" => new ByteArray("Blocks",$blocks),
					"Data" => new ByteArray("Data", isset($this->meta[$y]) ? $this->meta[$y] : str_repeat("\x00",2048)),
					"SkyLight" => new ByteArray("Data", isset($this->skyLight[$y]) ? $this->skyLight[$y] : str_repeat("\xff",2048)),
					"BlockLight" => new ByteArray("Data", isset($this->blockLight[$y]) ? $this->blockLight[$y] : str_repeat("\x00",2048)),
					]);
			}
			$nbt->BiomeColors = new IntArray("BiomeColors", $this->biomeColors);
			$nbt->HeightMap = new IntArray("HeightMap", $this->heightMap);
		}
		$writer = new NBT(NBT::BIG_ENDIAN);
		$writer->setData(new Compound("", ["Level" => $nbt]));
		return $writer->writeCompressed(ZLIB_ENCODING_DEFLATE, RegionLoader::$COMPRESSION_LEVEL);
	}
	public function getBlocks() {
		die("TODO --- implement this!\n");
	}
	public function getMeta() {
		die("TODO --- implement this!\n");
	}
	public function getBlockLight() {
		die("TODO --- implement this!\n");
	}
	public function getSkyLight() {
		die("TODO --- implement this!\n");
	}
}
