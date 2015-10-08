<?php
namespace pmimporter\generic;
use pmimporter\generic\BaseChunk;
use pmimporter\LevelFormat;
use pmsrc\utils\Binary;
use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\Byte;
use pmsrc\nbt\tag\ByteArray;
use pmsrc\nbt\tag\Compound;
use pmsrc\nbt\tag\Enum;
use pmsrc\nbt\tag\Int;
use pmsrc\nbt\tag\IntArray;
use pmsrc\nbt\tag\Long;

abstract class PcChunk extends BaseChunk {
	const VERSION = 1;

	protected static function fromNBT(Compound $nbt,$yoff = 0) {
		$data = [];
		$data["x"] = $nbt->xPos->getValue();
		$data["z"] = $nbt->zPos->getValue();
		if (isset($nbt->Biomes) && ($nbt->Biomes instanceof ByteArray)) {
			$biomes = $nbt->Biomes->getValue();
			if (strlen($biomes) == 256) $data["biomes"] = $biomes;
		}
		if (isset($nbt->BiomeColors) && ($nbt->BiomeColors instanceof IntArray)) {
			$colors = $nbt->BiomeColors->getValue();
			if (count($colors) == 256) $data["biomeColors"] = $colors;
		}
		if (isset($nbt->HeightMap) && ($nbt->HeightMap instanceof IntArray)) {
			$heights = $nbt->HeightMap->getValue();
			if (count($heights) == 256) {
				if ($yoff == 0) {
					$data["heightMap"] = $heights;
				} else {
					
				}
			}
		}
		if (isset($nbt->TerrainGenerated)) $data["isGenerated"] = $nbt["TerrainGenerated"] > 0;
		if (isset($nbt->TerrainPopulated)) $data["isPopulated"] = $nbt["TerrainPopulated"] > 0;
		if (isset($nbt->LightPopulated)) $data["isLightPopulated"] = $nbt["LightPopulated"] > 0;
		return $data;
	}
	protected static function addNBT($name, &$nbts) {
		$clones = [];
		foreach ($nbts as $n) {
			$clones[] = clone $n;
		}
		$nbt = new Enum($name, $clones);
	  $nbt->setTagType(NBT::TAG_Compound);
		return $nbt;
	}
	protected function initNBT() {
		$nbt = new Compound("Level", []);

		$nbt->xPos = new Int("xPos", $this->xPos);
		$nbt->zPos = new Int("zPos", $this->zPos);
		$nbt->TerrainGenerated = new Byte("TerrainGenerated",(int)$this->generated);
		$nbt->TerrainPopulated = new Byte("TerrainPopulated",(int)$this->populated);
		$nbt->LightPopulated = new Byte("LightPopulated",(int)$this->lighted);

		$nbt->LastUpdate = new Long("LastUpdate", 0);
		$nbt->V = new Byte("V", self::VERSION);
		$nbt->InhabitedTime = new Long("InhabitedTime", 0);
		$nbt->Biomes = new ByteArray("Biomes", str_repeat(Binary::writeByte(-1), 256));
		$nbt->TileTicks = new Enum("TileTicks", []);
		$nbt->TileTicks->setTagType(NBT::TAG_Compound);
		$nbt->setName("Level");

		return $nbt;
	}
	abstract public function toBinary();
}
