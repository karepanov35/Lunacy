<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

class Seagrass implements Populator{
	private int $randomAmount = 10;
	private int $baseAmount = 5;

	public function setRandomAmount(int $amount) : void{
		$this->randomAmount = $amount;
	}

	public function setBaseAmount(int $amount) : void{
		$this->baseAmount = $amount;
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$amount = $random->nextRange(0, $this->randomAmount) + $this->baseAmount;

		$water = VanillaBlocks::WATER()->getStateId();
		$seagrass = VanillaBlocks::SEAGRASS()->getStateId();

		for($i = 0; $i < $amount; ++$i){
			$x = $random->nextRange($chunkX * Chunk::EDGE_LENGTH, $chunkX * Chunk::EDGE_LENGTH + (Chunk::EDGE_LENGTH - 1));
			$z = $random->nextRange($chunkZ * Chunk::EDGE_LENGTH, $chunkZ * Chunk::EDGE_LENGTH + (Chunk::EDGE_LENGTH - 1));
			
			// Find water surface
			for($y = 62; $y >= 40; --$y){
				$block = $world->getBlockAt($x, $y, $z)->getStateId();
				$blockBelow = $world->getBlockAt($x, $y - 1, $z);
				
				// If we found water and block below is solid (sand, dirt, gravel, stone)
				if($block === $water && 
				   ($blockBelow->isSolid() || $blockBelow->getTypeId() === VanillaBlocks::SAND()->getTypeId() || 
				    $blockBelow->getTypeId() === VanillaBlocks::GRAVEL()->getTypeId() ||
				    $blockBelow->getTypeId() === VanillaBlocks::DIRT()->getTypeId())){
					// Place seagrass
					$world->setBlockAt($x, $y, $z, VanillaBlocks::SEAGRASS());
					break;
				}
			}
		}
	}
}
