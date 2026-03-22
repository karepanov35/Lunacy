<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator\lumi;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\generator\VanillaPopulator;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/**
 * Задаёт biome id по колонке и заменяет верхние блоки под стиль Lumi (cover/middle).
 */
final class NetherBiomeSurfacePopulator implements VanillaPopulator{

	public function __construct(
		private LumiNetherBiomePicker $picker
	){}

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$minY = $world->getMinY();
		$maxY = $world->getMaxY();
		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$wx = $cx + $x;
				$wz = $cz + $z;
				$biomeId = $this->picker->pickBiome($wx, $wz);

				for($y = $minY; $y < $maxY; ++$y){
					$chunk->setBiomeId($x, $y, $z, $biomeId);
				}

				$this->applySurfaceColumn($world, $random, $wx, $wz, $biomeId, $minY, $maxY);
			}
		}
	}

	private function applySurfaceColumn(ChunkManager $world, Random $random, int $wx, int $wz, int $biomeId, int $minY, int $maxY) : void{
		if($biomeId === BiomeIds::HELL){
			return;
		}

		for($y = $maxY - 1; $y > $minY; --$y){
			$here = $world->getBlockAt($wx, $y, $wz)->getTypeId();
			$above = $world->getBlockAt($wx, $y + 1, $wz)->getTypeId();
			if($above !== BlockTypeIds::AIR){
				continue;
			}

			// Раньше 5 слоёв давали отвесные «стены» на границе биомов; 2 слоя — мягче переход.
			$depth = match ($biomeId) {
				BiomeIds::SOULSAND_VALLEY, BiomeIds::BASALT_DELTAS => 2,
				BiomeIds::CRIMSON_FOREST, BiomeIds::WARPED_FOREST => 1,
				default => 1,
			};

			for($d = 0; $d < $depth && $y - $d > $minY; ++$d){
				$yy = $y - $d;
				$tid = $world->getBlockAt($wx, $yy, $wz)->getTypeId();
				if($tid === BlockTypeIds::BEDROCK){
					break;
				}

				match ($biomeId) {
					BiomeIds::CRIMSON_FOREST => $this->paintCrimson($world, $tid, $wx, $yy, $wz, $d),
					BiomeIds::WARPED_FOREST => $this->paintWarped($world, $tid, $wx, $yy, $wz, $d),
					BiomeIds::SOULSAND_VALLEY => $this->paintSoulSandValley($world, $tid, $wx, $yy, $wz),
					BiomeIds::BASALT_DELTAS => $this->paintBasaltDeltas($world, $random, $tid, $wx, $yy, $wz, $d),
					default => null,
				};
			}

			return;
		}
	}

	private function paintCrimson(ChunkManager $world, int $tid, int $x, int $y, int $z, int $d) : void{
		if($d === 0 && $this->canGrowNylium($tid)){
			$world->setBlockAt($x, $y, $z, VanillaBlocks::CRIMSON_NYLIUM());
		}
	}

	private function paintWarped(ChunkManager $world, int $tid, int $x, int $y, int $z, int $d) : void{
		if($d === 0 && $this->canGrowNylium($tid)){
			$world->setBlockAt($x, $y, $z, VanillaBlocks::WARPED_NYLIUM());
		}
	}

	/** Поверхность для нилия: незерак, гравий, иногда базальт после соседних биомов. */
	private function canGrowNylium(int $tid) : bool{
		return $tid === BlockTypeIds::NETHERRACK
			|| $tid === BlockTypeIds::GRAVEL
			|| $tid === BlockTypeIds::BASALT
			|| $tid === BlockTypeIds::BLACKSTONE;
	}

	private function paintSoulSandValley(ChunkManager $world, int $tid, int $x, int $y, int $z) : void{
		if($this->isReplaceableNetherTerrain($tid)){
			$world->setBlockAt($x, $y, $z, VanillaBlocks::SOUL_SAND());
		}
	}

	private function paintBasaltDeltas(ChunkManager $world, Random $random, int $tid, int $x, int $y, int $z, int $d) : void{
		if(!$this->isReplaceableNetherTerrain($tid)){
			return;
		}
		if($d === 0 && $random->nextBoundedInt(8) === 0){
			$world->setBlockAt($x, $y, $z, VanillaBlocks::BLACKSTONE());
		}else{
			$world->setBlockAt($x, $y, $z, VanillaBlocks::BASALT());
		}
	}

	private function isReplaceableNetherTerrain(int $typeId) : bool{
		return $typeId === BlockTypeIds::NETHERRACK
			|| $typeId === BlockTypeIds::GRAVEL
			|| $typeId === BlockTypeIds::SOUL_SAND
			|| $typeId === BlockTypeIds::SOUL_SOIL
			|| $typeId === BlockTypeIds::BASALT
			|| $typeId === BlockTypeIds::BLACKSTONE;
	}
}
