<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

class Lake implements Populator{
	private int $randomAmount = 50; // Very rare - 1 in 50 chance
	private int $baseAmount = 0;

	public function setRandomAmount(int $amount) : void{
		$this->randomAmount = $amount;
	}

	public function setBaseAmount(int $amount) : void{
		$this->baseAmount = $amount;
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		// Very rare lakes - only 1 in randomAmount chunks
		if($random->nextRange(0, $this->randomAmount) !== 0){
			return;
		}

		$amount = $this->baseAmount + 1;

		for($i = 0; $i < $amount; ++$i){
			// Stay within chunk boundaries to avoid accessing unloaded chunks
			$x = $random->nextRange($chunkX * Chunk::EDGE_LENGTH + 5, $chunkX * Chunk::EDGE_LENGTH + (Chunk::EDGE_LENGTH - 6));
			$z = $random->nextRange($chunkZ * Chunk::EDGE_LENGTH + 5, $chunkZ * Chunk::EDGE_LENGTH + (Chunk::EDGE_LENGTH - 6));
			
			// Find surface level
			$y = $this->getHighestWorkableBlock($world, $x, $z);
			if($y === -1 || $y < 62 || $y > 75) continue;

			// Check if area is relatively flat (for natural lake placement)
			if(!$this->isAreaFlat($world, $x, $y, $z, 4)){
				continue;
			}

			// Random lake depth (2-4 blocks) - shallower than before
			$depth = $random->nextRange(2, 4);
			
			// Random lake size (radius 4-6 blocks) - smaller
			$radius = $random->nextRange(4, 6);
			
			$this->createLake($world, $x, $y, $z, $radius, $depth, $random);
		}
	}

	private function isAreaFlat(ChunkManager $world, int $centerX, int $centerY, int $centerZ, int $checkRadius) : bool{
		$minY = $centerY;
		$maxY = $centerY;
		
		// Only check within current chunk to avoid accessing unloaded chunks
		$chunkX = $centerX >> 4;
		$chunkZ = $centerZ >> 4;
		
		for($x = $centerX - $checkRadius; $x <= $centerX + $checkRadius; ++$x){
			for($z = $centerZ - $checkRadius; $z <= $centerZ + $checkRadius; ++$z){
				// Skip if outside current chunk
				if(($x >> 4) !== $chunkX || ($z >> 4) !== $chunkZ){
					continue;
				}
				
				$y = $this->getHighestWorkableBlock($world, $x, $z);
				if($y === -1) return false;
				
				$minY = min($minY, $y);
				$maxY = max($maxY, $y);
			}
		}
		
		// Area must be relatively flat (max 3 blocks difference)
		return ($maxY - $minY) <= 3;
	}

	private function createLake(ChunkManager $world, int $centerX, int $centerY, int $centerZ, int $radius, int $depth, Random $random) : void{
		$water = VanillaBlocks::WATER();
		
		// Get current chunk coordinates
		$chunkX = $centerX >> 4;
		$chunkZ = $centerZ >> 4;
		
		// Create lake with natural oval shape
		for($x = $centerX - $radius; $x <= $centerX + $radius; ++$x){
			for($z = $centerZ - $radius; $z <= $centerZ + $radius; ++$z){
				// Skip if outside current chunk to avoid accessing unloaded chunks
				if(($x >> 4) !== $chunkX || ($z >> 4) !== $chunkZ){
					continue;
				}
				
				$dx = $x - $centerX;
				$dz = $z - $centerZ;
				
				// Oval shape calculation
				$distanceSquared = ($dx * $dx) + ($dz * $dz * 0.8);
				$radiusSquared = $radius * $radius;
				
				// Create smooth edges with randomness
				if($distanceSquared <= $radiusSquared - $random->nextRange(0, 3)){
					// Calculate depth based on distance from center (deeper in middle)
					$distanceFactor = 1.0 - ($distanceSquared / $radiusSquared);
					$actualDepth = max(1, (int)($depth * $distanceFactor));
					
					// Get surface height at this position
					$surfaceY = $this->getHighestWorkableBlock($world, $x, $z);
					if($surfaceY === -1) continue;
					
					// Dig down and fill with water
					for($y = $surfaceY; $y > $surfaceY - $actualDepth; --$y){
						if($y > 0 && $y <= $surfaceY){
							$block = $world->getBlockAt($x, $y, $z);
							$blockType = $block->getTypeId();
							
							// Only replace grass, dirt, stone - not trees or other structures
							if($blockType === BlockTypeIds::GRASS || 
							   $blockType === BlockTypeIds::DIRT ||
							   $blockType === BlockTypeIds::STONE){
								$world->setBlockAt($x, $y, $z, $water);
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
			if($b !== BlockTypeIds::AIR && $b !== BlockTypeIds::SNOW_LAYER && $b !== BlockTypeIds::LEAVES){
				return $y;
			}
		}

		return -1;
	}
}
