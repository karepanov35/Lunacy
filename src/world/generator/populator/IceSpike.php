<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

class IceSpike implements Populator{
	private int $randomAmount = 2;
	private int $baseAmount = 0;

	public function setRandomAmount(int $amount) : void{
		$this->randomAmount = $amount;
	}

	public function setBaseAmount(int $amount) : void{
		$this->baseAmount = $amount;
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$amount = $random->nextRange(0, $this->randomAmount) + $this->baseAmount;

		for($i = 0; $i < $amount; ++$i){
			$x = $random->nextRange($chunkX * Chunk::EDGE_LENGTH, $chunkX * Chunk::EDGE_LENGTH + (Chunk::EDGE_LENGTH - 1));
			$z = $random->nextRange($chunkZ * Chunk::EDGE_LENGTH, $chunkZ * Chunk::EDGE_LENGTH + (Chunk::EDGE_LENGTH - 1));
			$y = $this->getHighestWorkableBlock($world, $x, $z);

			if($y !== -1 && $y > 60){
				// Random spike type: 70% tall thin spikes, 30% shorter wide spikes
				if($random->nextBoundedInt(10) < 7){
					// Tall thin spike (15-30 blocks) - like on screenshot
					$height = $random->nextRange(15, 30);
					$this->createTallIceSpike($world, $x, $y, $z, $height, $random);
				}else{
					// Shorter wide spike (8-15 blocks)
					$height = $random->nextRange(8, 15);
					$this->createWideIceSpike($world, $x, $y, $z, $height, $random);
				}
			}
		}
	}

	private function createTallIceSpike(ChunkManager $world, int $x, int $y, int $z, int $height, Random $random) : void{
		$packedIce = VanillaBlocks::PACKED_ICE();

		// Create very tall and SHARP spike (заостренный)
		for($currentY = 0; $currentY < $height; ++$currentY){
			// Calculate radius - sharp taper from bottom to top
			$progress = $currentY / $height;
			
			if($currentY < 2){
				// Base (2 blocks radius)
				$radius = 2;
			}else{
				// Sharp taper - gets thinner quickly
				$radius = (int)((1 - $progress) * 2);
				if($radius < 1) $radius = 1;
			}

			for($offsetX = -$radius; $offsetX <= $radius; ++$offsetX){
				for($offsetZ = -$radius; $offsetZ <= $radius; ++$offsetZ){
					$distance = sqrt($offsetX * $offsetX + $offsetZ * $offsetZ);

					// Create circular cross-section
					if($distance <= $radius){
						$blockX = $x + $offsetX;
						$blockZ = $z + $offsetZ;
						$blockY = $y + $currentY;

						if($blockY < 256){
							$block = $world->getBlockAt($blockX, $blockY, $blockZ);
							if($block->getTypeId() === BlockTypeIds::AIR || 
							   $block->getTypeId() === BlockTypeIds::SNOW_LAYER){
								$world->setBlockAt($blockX, $blockY, $blockZ, $packedIce);
							}
						}
					}
				}
			}
		}
	}

	private function createWideIceSpike(ChunkManager $world, int $x, int $y, int $z, int $height, Random $random) : void{
		$packedIce = VanillaBlocks::PACKED_ICE();

		// Create shorter but wider spike
		for($currentY = 0; $currentY < $height; ++$currentY){
			// Calculate radius based on height (wider at bottom, narrower at top)
			$progress = $currentY / $height;
			$radius = (int)(4 * (1 - $progress * 0.7)) + 1;

			for($offsetX = -$radius; $offsetX <= $radius; ++$offsetX){
				for($offsetZ = -$radius; $offsetZ <= $radius; ++$offsetZ){
					$distance = sqrt($offsetX * $offsetX + $offsetZ * $offsetZ);

					// Create circular cross-section with some randomness
					if($distance <= $radius + $random->nextFloat() - 0.5){
						$blockX = $x + $offsetX;
						$blockZ = $z + $offsetZ;
						$blockY = $y + $currentY;

						if($blockY < 256){
							$block = $world->getBlockAt($blockX, $blockY, $blockZ);
							if($block->getTypeId() === BlockTypeIds::AIR || 
							   $block->getTypeId() === BlockTypeIds::SNOW_LAYER){
								$world->setBlockAt($blockX, $blockY, $blockZ, $packedIce);
							}
						}
					}
				}
			}
		}
	}

	private function getHighestWorkableBlock(ChunkManager $world, int $x, int $z) : int{
		$chunk = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);
		if($chunk === null) return -1;

		$highestBlock = $chunk->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
		if($highestBlock === null) return -1;

		for($y = $highestBlock; $y >= 0; --$y){
			$b = $world->getBlockAt($x, $y, $z)->getTypeId();
			if($b !== BlockTypeIds::AIR && $b !== BlockTypeIds::SNOW_LAYER){
				return $y + 1;
			}
		}

		return -1;
	}
}
