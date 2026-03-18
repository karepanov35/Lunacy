<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

class Flower implements Populator{
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

			if($y !== -1 && $this->canFlowerStay($world, $x, $y, $z)){
				// Random flower/plant selection
				$flowerType = $random->nextBoundedInt(20);
				
				if($flowerType < 3){ // 15% chance for double tall flowers (rose bush or lilac)
					$doubleTallType = $random->nextBoundedInt(3);
					if($doubleTallType === 0){
						// Rose Bush (pink bush)
						$this->placeDoubleTallFlower($world, $x, $y, $z, VanillaBlocks::ROSE_BUSH());
					}elseif($doubleTallType === 1){
						// Lilac (syringe)
						$this->placeDoubleTallFlower($world, $x, $y, $z, VanillaBlocks::LILAC());
					}else{
						// Large Fern (раскидистый папоротник)
						$this->placeDoubleTallFlower($world, $x, $y, $z, VanillaBlocks::LARGE_FERN());
					}
				}else{ // 85% chance for single flowers and plants
					$singleFlowerType = $random->nextBoundedInt(6);
					if($singleFlowerType === 0){
						// Oxeye Daisy (ромашка)
						$world->setBlockAt($x, $y, $z, VanillaBlocks::OXEYE_DAISY());
					}elseif($singleFlowerType === 1){
						// Poppy (red flower)
						$world->setBlockAt($x, $y, $z, VanillaBlocks::POPPY());
					}elseif($singleFlowerType === 2){
						// Dandelion (yellow flower)
						$world->setBlockAt($x, $y, $z, VanillaBlocks::DANDELION());
					}elseif($singleFlowerType === 3){
						// Allium (лук - фиолетовый цветок)
						$world->setBlockAt($x, $y, $z, VanillaBlocks::ALLIUM());
					}elseif($singleFlowerType === 4){
						// Cornflower (голубой василек)
						$world->setBlockAt($x, $y, $z, VanillaBlocks::CORNFLOWER());
					}else{
						// Fern (папоротник)
						$world->setBlockAt($x, $y, $z, VanillaBlocks::FERN());
					}
				}
			}
		}
	}

	private function placeDoubleTallFlower(ChunkManager $world, int $x, int $y, int $z, \pocketmine\block\Block $flower) : void{
		// Check if there's space for the top half
		if($world->getBlockAt($x, $y + 1, $z)->getTypeId() === BlockTypeIds::AIR){
			$world->setBlockAt($x, $y, $z, (clone $flower)->setTop(false));
			$world->setBlockAt($x, $y + 1, $z, (clone $flower)->setTop(true));
		}
	}

	private function canFlowerStay(ChunkManager $world, int $x, int $y, int $z) : bool{
		$currentBlock = $world->getBlockAt($x, $y, $z)->getTypeId();
		$groundBlock = $world->getBlockAt($x, $y - 1, $z)->getTypeId();
		
		// Flowers cannot grow in snowy biomes (where snow layer is on top of grass)
		// They can only grow on pure grass blocks without snow
		if($currentBlock === BlockTypeIds::SNOW_LAYER){
			return false; // Don't replace snow with flowers
		}
		
		// Flowers can only grow on grass blocks
		return $currentBlock === BlockTypeIds::AIR && $groundBlock === BlockTypeIds::GRASS;
	}

	private function getHighestWorkableBlock(ChunkManager $world, int $x, int $z) : int{
		$highestBlock = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)?->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
		if($highestBlock === null){
			return -1;
		}
		for($y = $highestBlock; $y >= 0; --$y){
			$b = $world->getBlockAt($x, $y, $z);
			if($b->getTypeId() !== BlockTypeIds::AIR && !($b instanceof Leaves) && $b->getTypeId() !== BlockTypeIds::SNOW_LAYER){
				return $y + 1;
			}
		}

		return -1;
	}
}
