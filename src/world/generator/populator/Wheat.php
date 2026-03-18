<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

class Wheat implements Populator{
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
			// 15% chance to spawn single wheat block
			if($random->nextBoundedInt(100) >= 15){
				continue;
			}

			$x = $random->nextRange($chunkX * Chunk::EDGE_LENGTH, $chunkX * Chunk::EDGE_LENGTH + (Chunk::EDGE_LENGTH - 1));
			$z = $random->nextRange($chunkZ * Chunk::EDGE_LENGTH, $chunkZ * Chunk::EDGE_LENGTH + (Chunk::EDGE_LENGTH - 1));
			$y = $this->getHighestWorkableBlock($world, $x, $z);

			if($y !== -1 && $this->canWheatGrow($world, $x, $y, $z)){
				// Place single wheat block (fully grown)
				$wheat = VanillaBlocks::WHEAT()->setAge(7);
				$farmland = VanillaBlocks::FARMLAND();
				
				$world->setBlockAt($x, $y - 1, $z, $farmland);
				$world->setBlockAt($x, $y, $z, $wheat);
			}
		}
	}

	private function createWheatPatch(ChunkManager $world, int $centerX, int $centerY, int $centerZ, int $size, Random $random) : void{
		$wheat = VanillaBlocks::WHEAT()->setAge(7); // Fully grown wheat
		$farmland = VanillaBlocks::FARMLAND();

		$halfSize = (int)($size / 2);

		for($x = $centerX - $halfSize; $x <= $centerX + $halfSize; ++$x){
			for($z = $centerZ - $halfSize; $z <= $centerZ + $halfSize; ++$z){
				// Random gaps for natural look
				if($random->nextBoundedInt(10) < 2){
					continue;
				}

				$y = $this->getHighestWorkableBlock($world, $x, $z);
				if($y === -1) continue;

				if($this->canWheatGrow($world, $x, $y, $z)){
					// Place farmland and wheat
					$world->setBlockAt($x, $y - 1, $z, $farmland);
					$world->setBlockAt($x, $y, $z, $wheat);
				}
			}
		}
	}

	private function canWheatGrow(ChunkManager $world, int $x, int $y, int $z) : bool{
		$currentBlock = $world->getBlockAt($x, $y, $z)->getTypeId();
		$groundBlock = $world->getBlockAt($x, $y - 1, $z)->getTypeId();

		// Wheat can only grow on grass or dirt
		return $currentBlock === BlockTypeIds::AIR && 
		       ($groundBlock === BlockTypeIds::GRASS || $groundBlock === BlockTypeIds::DIRT);
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
