<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator\lumi;

use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\generator\VanillaPopulator;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/**
 * Аналог части {@code SoulSandValleyBiome} (Lumi): подмешивание soul soil в душу песка.
 */
final class SoulSoilMixPopulator implements VanillaPopulator{

	public function __construct(
		private LumiNetherBiomePicker $picker
	){}

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;
		$maxY = $world->getMaxY();

		for($i = 0; $i < 320; ++$i){
			$x = $random->nextBoundedInt(Chunk::EDGE_LENGTH);
			$z = $random->nextBoundedInt(Chunk::EDGE_LENGTH);
			$wx = $cx + $x;
			$wz = $cz + $z;
			if($this->picker->pickBiome($wx, $wz) !== BiomeIds::SOULSAND_VALLEY){
				continue;
			}
			$y = $random->nextBoundedInt($maxY - 2) + 1;
			if($chunk->getBlockStateId($x, $y, $z) !== VanillaBlocks::SOUL_SAND()->getStateId()){
				continue;
			}
			if($random->nextBoundedInt(4) === 0){
				$world->setBlockAt($wx, $y, $wz, VanillaBlocks::SOUL_SOIL());
			}
		}
	}
}
