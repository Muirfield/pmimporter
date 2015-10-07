<?php
namespace pmimporter\anvil;
use pmimporter\generic\PcChunk;
use pmimporter\RegionLoader;
use pmimporter\Chunk;
use pmimporter\LevelFormat;

use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\Byte;
use pmsrc\nbt\tag\ByteArray;
use pmsrc\nbt\tag\Compound;
use pmsrc\nbt\tag\Enum;
use pmsrc\nbt\tag\Int;
use pmsrc\nbt\tag\IntArray;
use pmsrc\nbt\tag\Long;

class AnvilChunk extends PcChunk {
	const SECTION_COUNT = 8;

	static public function fromBinary(LevelFormat $level, &$binary, $yoff = 0) {
		$reader = new NBT(NBT::BIG_ENDIAN);
		$reader->readCompressed($binary, ZLIB_ENCODING_DEFLATE);
		$chunk = $reader->getData();
		if(!isset($chunk->Level) or !($chunk->Level instanceof Compound)) return null;
		//echo "CHUNK: ".strlen($binary)." bytes - ";//##DEBUG
		//echo __METHOD__.",".__LINE__."\n";//##DEBUG
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
		//echo implode(", ",array_keys($data)). " : ";//##DEBUG

		$data["blocks"] = [];
		$data["meta"] = [];
		$data["blockLight"] = [];
		$data["skyLight"] = [];
		if (isset($nbt->Sections) && ($nbt->Sections instanceof Enum)) {
			if ($yoff == 0) {
				// Handle the simplest case
				//echo __METHOD__.",".__LINE__."\n";//##DEBUG
				foreach ($nbt->Sections as $section) {
					if (!($section instanceof Compound)) continue;
					$y = (int)$section["Y"];
					//echo "s($y)";
					$data["blocks"][$y] = (string)$section["Blocks"];
					$data["meta"][$y] =  (string)$section["Data"];
					$data["blockLight"][$y] =  (string)$section["BlockLight"];
					$data["skyLight"][$y] =  (string)$section["SkyLight"];
				}
			} else {
				// Handle Y offsetting

				// Figure out what sections are available
				$ptrs = [];
				foreach ($nbt->Sections as $section) {
					if (!($section instanceof Compound)) continue;
					$y = (int)$section["Y"];
					$ptrs[$y] = &$section;
				}

				// Slice up chunk
				$sects = [];
				$slices = [];
				for ($y =0; $y < PM_MAX_HEIGHT; $y++) {
					$srcy = $y - $yoff;
					$secy = $srcy >> 4;
					if (!isset($ptrs[$secy])) continue;
					if (!isset($sects[$secy])) {
						$sects[$secy] = [
							"blocks" => [],
							"meta" => [],
							"blockLight" => [],
							"skyLight" => [],
						];
						$i = 0;
						foreach (str_split((string)$ptrs[$secy]["Blocks"],256)  as $layer) {
							$sects[$secy]["blocks"][$i++] = $layer;
						}
						foreach (["meta"=>"Data","blockLight"=>"BlockLight","skyLight"=>"SkyLight"] as $d=>$s) {
							$i = 0;
							foreach (str_split((string)$ptrs[$secy][$s],128) as $layer) {
								$sects[$secy][$d][$i++] = $layer;
							}
						}
					}
					$i = $srcy & 0x0f;
					$slices[$y] = [];
					foreach (["blocks","meta","blockLight","skyLight"] as $j) {
						$slices[$y][$j] = $sects[$secy][$j][$i];
					}
				}

				// Used for empty slices...
				$empty = [];
				foreach (["blocks"=>["\x00",256],"meta"=>["\x00",128],"blockLight"=>["\x00",128],"skyLight"=>["\xff",128]] as $j=>$pad) {
					$empty[$j] = str_repeat($pad[0],$pad[1]);
				}

				// Assemble sections
				for($sy=0; $sy < self::SECTION_COUNT ; $sy++) {
					$y = $sy << 4;
					$j = false;
					for ($i =0; $i < 16;$i++) { // Check if section is empty
						if (isset($slices[$y+$i])) {
							$j = true;
							break;
						}
					}
					if (!$j) continue;

					// Assemble a single section
					foreach ($empty as $j => &$pad) {
						$data[$j][$sy] = "";
						for($i=0;$i<16;$i++) {
							$data[$j][$sy] .= isset($slices[$y+$i][$j]) ? $slices[$y+$i][$j] : $pad;
						}
					}
				}
				// Finnish re-assembling slices
			}
		}
		//echo "\n";
		return new AnvilChunk($level,$data);
	}
	public function __construct(LevelFormat $level,array &$data) {
		if (isset($data["blocks"]) && !is_array($data["blocks"])) {
			// Convert from ZXY to YZX...
			$data["blocks"] = $this->getBlockSections($data["blocks"]);
		}
		if (isset($data["meta"]) && !is_array($data["meta"])) {
			// Convert from ZXY to YZX...
			$data["meta"] = $this->getHalfSections($data["meta"],"\x00");
		}
		if (isset($data["blockLight"]) && !is_array($data["blockLight"])) {
			// Convert from ZXY to YZX...
			$data["blockLight"] = $this->getHalfSections($data["blockLight"],"\x00");
		}
		if (isset($data["skyLight"]) && !is_array($data["skyLight"])) {
			// Convert from ZXY to YZX...
			$data["skyLight"] = $this->getHalfSections($data["skyLight"],"\xff");
		}
		parent::__construct($level, $data);
	}
	public function toBinary() {
		$nbt = $this->initNBT();
		if (count($this->entities) > 0) $nbt->Entities = self::addNBT("Entities",$this->entities);
		if (count($this->tiles) > 0) $nbt->TileEntities = self::addNBT("TileEntities",$this->tiles);

		if ($this->isGenerated() && count($this->blocks) > 0) {
			// Create sections...
			$nbt->Sections = new Enum("Sections",[]);
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
	private function getBlockSection($blocks, $sy) {
		$ordered = "";
		$off = $sy<<4;
		for ($y = 0;$y < 16; $y++) {
			for ($z = 0;$z < 16; $z++) {
				for ($x = 0;$x < 16; $x++) {
					$ordered .= $blocks{($x<<11)|($z<<7)|($y + $off)};
				}
			}
		}
		return $ordered;
	}
	private function getBlockSections($zxyBlocks) {
		$yzxBlocks = [];
		$emptySection = str_repeat("\x00",4096);
		for ($y=0;$y< self::SECTION_COUNT;$y++) {
			$section = $this->getBlockSection($zxyBlocks,$y);
			if ($section == $emptySection) continue;
			$yzxBlocks[$y] = $section;
		}
		return $yzxBlocks;
	}
	private function getHalfSections($zxyData,$pad="\x00") {
		$yzxData = [];
		$emptySection = str_repeat($pad,2048);
		for ($y=0;$y< self::SECTION_COUNT;$y++) {
			$section = $this->getHalfSection($zxyData,$y);
			if ($section == $emptySection) continue;
			$yzxData[$y] = $section;
		}
		return $yzxData;
	}
	private function getHalfSection($data, $sy) {
		$ordered = "";
		$off = $sy<<3;
		for ($y = 0;$y < 16; $y++) {
			if (($y & 0x1) == 0) {
				for ($z = 0;$z < 16; $z++) {
					for ($x = 0;$x < 16; $x += 2) {
						$ordered .= chr(
							ord($data{($x<<10)|($z<<6)|(($y+$off)>>1)}) & 0x0f |
							(ord($data{(($x+1)<<10)|($z<<6)|(($y+$off)>>1)} & 0x0f) << 4)
						);
					}
				}
			} else {
				for ($z = 0;$z < 16; $z++) {
					for ($x = 0;$x < 16; $x += 2) {
						$ordered .= chr(
							((ord($data{($x<<10)|($z<<6)|(($y+$off)>>1)}) >> 4) & 0x0f) |
							(ord($data{(($x+1)<<10)|($z<<6)|(($y+$off)>>1)}) & 0xf0)
						);
					}
				}
			}
		}
		return $ordered;
	}
	private function getBlockIdArray() {
		$blocks = "";
		for($y=0; $y < self::SECTION_COUNT ; $y++) {
			if (isset($this->blocks[$y])) {
				$blocks .= $this->blocks[$y];
			} else {
				$blocks .=  str_repeat("\x00", 4096);
			}
		}
		return $blocks;
	}
	private function getMetaArray() {
		$meta = "";
		for($y=0; $y < self::SECTION_COUNT ; $y++) {
			if (isset($this->meta[$y])) {
				$meta .= $this->meta[$y];
			} else {
				$meta .=  str_repeat("\x00", 2048);
			}
		}
		return $meta;
	}
	private function getSkyLightArray() {
		$light = "";
		for($y=0; $y < self::SECTION_COUNT ; $y++) {
			if (isset($this->skyLight[$y])) {
				$light .= $this->skyLight[$y];
			} else {
				$light .=  str_repeat("\xff", 2048);
			}
		}
		return $light;
	}
	private function getBlockLightArray() {
		$light = "";
		for($y=0; $y < self::SECTION_COUNT ; $y++) {
			if (isset($this->blockLight[$y])) {
				$light .= $this->blockLight[$y];
			} else {
				$light .=  str_repeat("\x00", 2048);
			}
		}
		return $light;
	}
	private function getColumn($data,$x,$z) {
		$column = "";
		$i = ($z<<4)+$x;
		for($y=0;$y<PM_MAX_HEIGHT;$y++) {
			$column .= $data{($y<<8)+$i};
		}
		return $column;
	}
	public function getHalfColumn($data, $x, $z){
		$column = "";
		$i = ($z << 3) + ($x >> 1);
		if(($x & 1) === 0){
			for($y = 0; $y < 128; $y += 2){
				$column .= ($data{($y << 7) + $i} & "\x0f") | chr((ord($data{(($y + 1) << 7) + $i}) & 0x0f) << 4);
			}
		} else {
			for($y = 0; $y < 128; $y += 2){
				$column .= chr((ord($data{($y << 7) + $i}) & 0xf0) >> 4) | ($data{(($y + 1) << 7) + $i} & "\xf0");
			}
		}
		return $column;
	}

	public function getBlocks() {
		$yzxBlocks = $this->getBlockIdArray();
		$zxyBlocks = "";
		for ($x=0;$x<16;$x++) {
			for ($z=0;$z<16;$z++) {
				$zxyBlocks .= $this->getColumn($yzxBlocks,$x,$z);
			}
		}
		return $zxyBlocks;
	}
	public function getMeta() {
		$yzxMeta = $this->getMetaArray();
		$zxyMeta = "";
		for ($x=0;$x<16;$x++) {
			for ($z=0;$z<16;$z++) {
				$zxyMeta .= $this->getHalfColumn($yzxMeta,$x,$z);
			}
		}
		return $zxyMeta;
	}
	public function getBlockLight() {
		$yzxLight = $this->getBlockLightArray();
		$zxyLight = "";
		for ($x=0;$x<16;$x++) {
			for ($z=0;$z<16;$z++) {
				$zxyLight .= $this->getHalfColumn($yzxLight,$x,$z);
			}
		}
		return $zxyLight;
	}
	public function getSkyLight() {
		$yzxLight = $this->getSkyLightArray();
		$zxyLight = "";
		for ($x=0;$x<16;$x++) {
			for ($z=0;$z<16;$z++) {
				$zxyLight .= $this->getHalfColumn($yzxLight,$x,$z);
			}
		}
		return $zxyLight;
	}
}
