<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\decorator;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Decorator;

/**
 * Аметистовые жеоды — сферы из smooth basalt, calcite и аметиста под землёй.
 */
class AmethystGeodeDecorator extends Decorator{

	public function decorate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		if($random->nextBoundedInt(24) !== 0){
			return;
		}

		$minY = $world->getMinY();
		$maxY = $world->getMaxY();
		$centerY = $minY + 24 + $random->nextBoundedInt(max(1, $maxY - $minY - 48));
		$cx = ($chunk_x << Chunk::COORD_BIT_SIZE) + 8;
		$cz = ($chunk_z << Chunk::COORD_BIT_SIZE) + 8;

		$outerR = 6 + $random->nextBoundedInt(3);
		$middleR = (int) ($outerR * 0.7);
		$innerR = (int) ($outerR * 0.35);

		$stone = VanillaBlocks::STONE()->getStateId();

		$chunkMinX = $chunk_x << Chunk::COORD_BIT_SIZE;
		$chunkMinZ = $chunk_z << Chunk::COORD_BIT_SIZE;

		for($x = -$outerR - 1; $x <= $outerR + 1; $x++){
			for($z = -$outerR - 1; $z <= $outerR + 1; $z++){
				for($y = -$outerR - 1; $y <= $outerR + 1; $y++){
					$wx = $cx + $x;
					$wy = $centerY + $y;
					$wz = $cz + $z;
					if($wx < $chunkMinX || $wx >= $chunkMinX + 16 || $wz < $chunkMinZ || $wz >= $chunkMinZ + 16){
						continue;
					}
					$distSq = $x * $x + $y * $y + $z * $z;
					$rOut = $outerR + 0.5;
					$rMid = $middleR + 0.3;
					$rIn = $innerR + 0.3;
					$block = $world->getBlockAt($wx, $wy, $wz)->getStateId();
					if($block !== $stone){
						continue;
					}
					if($distSq <= $rOut * $rOut){
						if($distSq <= $rIn * $rIn){
							if($random->nextBoundedInt(5) === 0){
								$world->setBlockAt($wx, $wy, $wz, VanillaBlocks::BUDDING_AMETHYST());
							}else{
								$world->setBlockAt($wx, $wy, $wz, VanillaBlocks::AMETHYST());
							}
						}elseif($distSq <= $rMid * $rMid){
							$world->setBlockAt($wx, $wy, $wz, VanillaBlocks::CALCITE());
						}else{
							$world->setBlockAt($wx, $wy, $wz, VanillaBlocks::SMOOTH_BASALT());
						}
					}
				}
			}
		}

		for($i = 0; $i < 12; $i++){
			$dx = $random->nextBoundedInt(2 * $innerR + 1) - $innerR;
			$dy = $random->nextBoundedInt(2 * $innerR + 1) - $innerR;
			$dz = $random->nextBoundedInt(2 * $innerR + 1) - $innerR;
			if($dx * $dx + $dy * $dy + $dz * $dz > $innerR * $innerR){
				continue;
			}
			$wx = $cx + $dx;
			$wy = $centerY + $dy;
			$wz = $cz + $dz;
			if($wx < $chunkMinX || $wx >= $chunkMinX + 16 || $wz < $chunkMinZ || $wz >= $chunkMinZ + 16){
				continue;
			}
			$b = $world->getBlockAt($wx, $wy, $wz);
			if($b->getTypeId() === VanillaBlocks::AMETHYST()->getTypeId() || $b->getTypeId() === VanillaBlocks::BUDDING_AMETHYST()->getTypeId()){
				$world->setBlockAt($wx, $wy, $wz, VanillaBlocks::AMETHYST_CLUSTER());
			}
		}
	}
}
