<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

class DesertPlant implements Populator{
	private int $randomAmount = 3;
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

			if($y !== -1 && $this->canPlantStay($world, $x, $y, $z)){
				// 70% cactus, 30% dead bush
				if($random->nextBoundedInt(10) < 7){
					// Place cactus (1-4 blocks tall)
					$height = $random->nextRange(1, 4);
					$this->placeCactus($world, $x, $y, $z, $height);
				}else{
					// Place dead bush
					$world->setBlockAt($x, $y, $z, VanillaBlocks::DEAD_BUSH());
				}
			}
		}
	}

	private function placeCactus(ChunkManager $world, int $x, int $y, int $z, int $height) : void{
		$cactus = VanillaBlocks::CACTUS();
		
		// Check if there's enough space and no blocks adjacent
		for($i = 0; $i < $height; ++$i){
			$currentY = $y + $i;
			
			// Check if position is air
			if($world->getBlockAt($x, $currentY, $z)->getTypeId() !== BlockTypeIds::AIR){
				return;
			}
			
			// Cactus cannot have blocks directly adjacent (except at base)
			if($i > 0){
				if($world->getBlockAt($x + 1, $currentY, $z)->getTypeId() !== BlockTypeIds::AIR ||
				   $world->getBlockAt($x - 1, $currentY, $z)->getTypeId() !== BlockTypeIds::AIR ||
				   $world->getBlockAt($x, $currentY, $z + 1)->getTypeId() !== BlockTypeIds::AIR ||
				   $world->getBlockAt($x, $currentY, $z - 1)->getTypeId() !== BlockTypeIds::AIR){
					return;
				}
			}
		}
		
		// Place cactus blocks
		for($i = 0; $i < $height; ++$i){
			$world->setBlockAt($x, $y + $i, $z, $cactus);
		}
	}

	private function canPlantStay(ChunkManager $world, int $x, int $y, int $z) : bool{
		$currentBlock = $world->getBlockAt($x, $y, $z)->getTypeId();
		$groundBlock = $world->getBlockAt($x, $y - 1, $z)->getTypeId();
		
		// Plants can only grow on sand
		return $currentBlock === BlockTypeIds::AIR && 
		       ($groundBlock === BlockTypeIds::SAND || $groundBlock === BlockTypeIds::SANDSTONE);
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
