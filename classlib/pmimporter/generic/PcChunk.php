<?php
namespace pmimporter\generic;
use pmimporter\generic\BaseChunk;
use pmimporter\LevelFormat;
use pmsrc\utils\Binary;
use pmsrc\nbt\NBT;
use pmsrc\nbt\tag\ByteTag;
use pmsrc\nbt\tag\ByteArrayTag;
use pmsrc\nbt\tag\CompoundTag;
use pmsrc\nbt\tag\EnumTag;
use pmsrc\nbt\tag\IntTag;
use pmsrc\nbt\tag\IntArrayTag;
use pmsrc\nbt\tag\LongTag;

abstract class PcChunk extends BaseChunk {
	const VERSION = 1;

	protected static function fromNBT(CompoundTag $nbt,$yoff = 0) {
		$data = [];
		$data["x"] = $nbt->xPos->getValue();
		$data["z"] = $nbt->zPos->getValue();
		if (isset($nbt->Biomes) && ($nbt->Biomes instanceof ByteArrayTag)) {
			$biomes = $nbt->Biomes->getValue();
			if (strlen($biomes) == 256) $data["biomes"] = $biomes;
		}
		if (isset($nbt->BiomeColors) && ($nbt->BiomeColors instanceof IntArrayTag)) {
			$colors = $nbt->BiomeColors->getValue();
			if (count($colors) == 256) $data["biomeColors"] = $colors;
		}
		if (isset($nbt->HeightMap) && ($nbt->HeightMap instanceof IntArrayTag)) {
			$heights = $nbt->HeightMap->getValue();
			if (count($heights) == 256) {
				if ($yoff == 0) {
					$data["heightMap"] = $heights;
				} else {
					$data["heightMap"] = [];
					foreach ($heights as $h) {
						$h = $h + $yoff;
						if ($h < 0) {
							$h = 0;
						} elseif ($h >= PM_MAX_HEIGHT) {
							$h = PM_MAX_HEIGHT-1;
						}
						$data["heightMap"][] = $h;
					}
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
		$nbt = new EnumTag($name, $clones);
	  $nbt->setTagType(NBT::TAG_Compound);
		return $nbt;
	}
	protected function initNBT() {
		$nbt = new CompoundTag("Level", []);

		$nbt->xPos = new IntTag("xPos", $this->xPos);
		$nbt->zPos = new IntTag("zPos", $this->zPos);
		$nbt->TerrainGenerated = new ByteTag("TerrainGenerated",(int)$this->generated);
		$nbt->TerrainPopulated = new ByteTag("TerrainPopulated",(int)$this->populated);
		$nbt->LightPopulated = new ByteTag("LightPopulated",(int)$this->lighted);

		$nbt->LastUpdate = new LongTag("LastUpdate", 0);
		$nbt->V = new ByteTag("V", self::VERSION);
		$nbt->InhabitedTime = new LongTag("InhabitedTime", 0);
		$nbt->Biomes = new ByteArrayTag("Biomes", str_repeat(Binary::writeByte(-1), 256));
		$nbt->TileTicks = new EnumTag("TileTicks", []);
		$nbt->TileTicks->setTagType(NBT::TAG_Compound);
		$nbt->setName("Level");

		return $nbt;
	}
	abstract public function toBinary();
}
