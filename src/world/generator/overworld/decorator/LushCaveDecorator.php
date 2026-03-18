<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\decorator;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Decorator;

/**
 * Заросшие пещеры — подземные карманы из мха (moss block).
 */
class LushCaveDecorator extends Decorator{

	public function decorate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		if($random->nextBoundedInt(18) !== 0){
			return;
		}

		$minY = $world->getMinY();
		$maxY = $world->getMaxY();
		$centerY = $minY + 20 + $random->nextBoundedInt(max(1, $maxY - $minY - 40));
		$cx = ($chunk_x << Chunk::COORD_BIT_SIZE) + 4 + $random->nextBoundedInt(8);
		$cz = ($chunk_z << Chunk::COORD_BIT_SIZE) + 4 + $random->nextBoundedInt(8);

		$radius = 2 + $random->nextBoundedInt(3);
		$stone = VanillaBlocks::STONE()->getStateId();
		$chunkMinX = $chunk_x << Chunk::COORD_BIT_SIZE;
		$chunkMinZ = $chunk_z << Chunk::COORD_BIT_SIZE;

		for($x = -$radius; $x <= $radius; $x++){
			for($z = -$radius; $z <= $radius; $z++){
				for($y = -$radius; $y <= $radius; $y++){
					$wx = $cx + $x;
					$wy = $centerY + $y;
					$wz = $cz + $z;
					if($wx < $chunkMinX || $wx >= $chunkMinX + 16 || $wz < $chunkMinZ || $wz >= $chunkMinZ + 16){
						continue;
					}
					$distSq = $x * $x + $y * $y + $z * $z;
					if($distSq <= $radius * $radius){
						$block = $world->getBlockAt($wx, $wy, $wz)->getStateId();
						if($block === $stone){
							$world->setBlockAt($wx, $wy, $wz, VanillaBlocks::MOSS_BLOCK());
						}
					}
				}
			}
		}
	}
}
