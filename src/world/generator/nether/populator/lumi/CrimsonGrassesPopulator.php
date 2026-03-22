<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator\lumi;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\generator\VanillaPopulator;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/** Порт {@code CrimsonGrassesPopulator} (Lumi). */
final class CrimsonGrassesPopulator implements VanillaPopulator{

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$amount = $random->nextBoundedInt(128) + 192;
		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;

		for($i = 0; $i < $amount; ++$i){
			$x = $cx + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
			$z = $cz + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
			foreach($this->getHighestWorkableBlocks($world, $x, $z) as $y){
				if($y <= 1){
					continue;
				}
				$block = $random->nextBoundedInt(6) === 0
					? VanillaBlocks::CRIMSON_FUNGUS()
					: VanillaBlocks::CRIMSON_ROOTS();
				$world->setBlockAt($x, $y, $z, $block);
			}
		}
	}

	/**
	 * @return int[]
	 */
	private function getHighestWorkableBlocks(ChunkManager $world, int $x, int $z) : array{
		$ys = [];
		for($y = $world->getMaxY() - 1; $y > 0; --$y){
			$b = $world->getBlockAt($x, $y, $z)->getTypeId();
			if($b === BlockTypeIds::CRIMSON_NYLIUM && $world->getBlockAt($x, $y + 1, $z)->getTypeId() === BlockTypeIds::AIR){
				$ys[] = $y + 1;
			}
		}
		return $ys;
	}
}
