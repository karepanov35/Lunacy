<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

class Cactus implements Populator{
	private int $randomAmount = 0;
	private int $baseAmount = 0;

	public function setRandomAmount(int $amount) : void{
		$this->randomAmount = $amount;
	}

	public function setBaseAmount(int $amount) : void{
		$this->baseAmount = $amount;
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$amount = $random->nextRange(0, $this->randomAmount + 1) + $this->baseAmount;
		
		for($i = 0; $i < $amount; ++$i){
			$x = $random->nextRange($chunkX * 16, $chunkX * 16 + 15);
			$z = $random->nextRange($chunkZ * 16, $chunkZ * 16 + 15);
			$y = $this->getHighestWorkableBlock($world, $x, $z);
			
			if($y !== -1){
				$tallRand = $random->nextRange(0, 17);
				$yMax = $y + 1 + (int)($tallRand > 10) + (int)($tallRand > 15);

				for(; $y < 127 && $y < $yMax; $y++){
					if($this->canCactusStay($world, $x, $y, $z)){
						$world->setBlockAt($x, $y, $z, VanillaBlocks::CACTUS());
					}
				}
			}
		}
	}

	private function canCactusStay(ChunkManager $world, int $x, int $y, int $z) : bool{
		$block = $world->getBlockAt($x, $y, $z);
		$below = $world->getBlockAt($x, $y - 1, $z);
		
		$adjacentBlocks = [
			$world->getBlockAt($x + 1, $y, $z),
			$world->getBlockAt($x - 1, $y, $z),
			$world->getBlockAt($x, $y, $z + 1),
			$world->getBlockAt($x, $y, $z - 1)
		];
		
		foreach($adjacentBlocks as $adjacent){
			if(!$adjacent->canBeReplaced()){
				return false;
			}
		}
		
		$belowIsSand = $below->hasSameTypeId(VanillaBlocks::SAND());
		$belowIsCactus = $below->hasSameTypeId(VanillaBlocks::CACTUS());
		
		return $block->canBeReplaced() && ($belowIsSand || $belowIsCactus);
	}

	private function getHighestWorkableBlock(ChunkManager $world, int $x, int $z) : int{
		for($y = 127; $y >= 0; --$y){
			$block = $world->getBlockAt($x, $y, $z);
			if(!$block->canBeReplaced() && 
			   !$block->hasSameTypeId(VanillaBlocks::OAK_LEAVES()) &&
			   !$block->hasSameTypeId(VanillaBlocks::BIRCH_LEAVES()) &&
			   !$block->hasSameTypeId(VanillaBlocks::SNOW_LAYER())){
				break;
			}
		}
		return $y === 0 ? -1 : ++$y;
	}
}
