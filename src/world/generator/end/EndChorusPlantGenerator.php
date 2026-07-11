<?php

declare(strict_types=1);

namespace pocketmine\world\generator\end;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function abs;

final class EndChorusPlantGenerator{

	private const MAX_BRANCH_DISTANCE = 8;

	public function __construct(
		private readonly TheEndGenerator $generator
	){}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		if(($chunkX * $chunkX + $chunkZ * $chunkZ) <= 4096){
			return;
		}

		if($this->generator->getIslandHeight($chunkX, $chunkZ, 1, 1) <= 40.0){
			return;
		}

		$attempts = $random->nextBoundedInt(5);
		for($i = 0; $i < $attempts; ++$i){
			$baseX = ($chunkX << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16) + 8;
			$baseZ = ($chunkZ << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16) + 8;
			$baseY = $this->findSurface($world, $baseX, $baseZ);
			if($baseY < 0){
				continue;
			}

			$air = VanillaBlocks::AIR();
			if(
				!$world->getBlockAt($baseX, $baseY, $baseZ)->hasSameTypeId($air) ||
				$world->getBlockAt($baseX, $baseY - 1, $baseZ)->getTypeId() !== BlockTypeIds::END_STONE
			){
				continue;
			}

			$this->generate($world, $baseX, $baseY, $baseZ, $random);
		}
	}

	private function findSurface(ChunkManager $world, int $x, int $z) : int{
		$highestBlock = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)?->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
		if($highestBlock === null){
			return -1;
		}

		for($y = $highestBlock; $y >= $world->getMinY(); --$y){
			$block = $world->getBlockAt($x, $y, $z);
			if($block->getTypeId() === BlockTypeIds::END_STONE){
				return $y + 1;
			}
			if($block->getTypeId() !== BlockTypeIds::AIR){
				return -1;
			}
		}

		return -1;
	}

	private function generate(ChunkManager $world, int $x, int $y, int $z, Random $random) : void{
		$plant = VanillaBlocks::CHORUS_PLANT();
		$world->setBlockAt($x, $y, $z, $plant);
		$this->grow($world, $x, $y, $z, $x, $y, $z, self::MAX_BRANCH_DISTANCE, 0, $random);
	}

	private function canGrow(ChunkManager $world, int $x, int $y, int $z, int $face) : bool{
		$air = BlockTypeIds::AIR;
		if($face !== 0 && $world->getBlockAt($x - 1, $y, $z)->getTypeId() !== $air){
			return false;
		}
		if($face !== 1 && $world->getBlockAt($x + 1, $y, $z)->getTypeId() !== $air){
			return false;
		}
		if($face !== 2 && $world->getBlockAt($x, $y, $z - 1)->getTypeId() !== $air){
			return false;
		}
		return !($face !== 3 && $world->getBlockAt($x, $y, $z + 1)->getTypeId() !== $air);
	}

	private function grow(
		ChunkManager $world,
		int $targetX,
		int $targetY,
		int $targetZ,
		int $sourceX,
		int $sourceY,
		int $sourceZ,
		int $maxDistance,
		int $age,
		Random $random
	) : void{
		$plant = VanillaBlocks::CHORUS_PLANT();
		$flower = VanillaBlocks::CHORUS_FLOWER()->setAge(5);

		$height = $random->nextBoundedInt(4) + 1;
		if($age === 0){
			++$height;
		}

		for($i = 0; $i < $height; ++$i){
			$growY = $targetY + $i + 1;
			if(!$this->canGrow($world, $targetX, $growY, $targetZ, -1)){
				return;
			}
			$world->setBlockAt($targetX, $growY, $targetZ, $plant);
		}

		$branched = false;
		if($age < 4){
			$branchCount = $random->nextBoundedInt(4);
			if($age === 0){
				++$branchCount;
			}

			for($i = 0; $i < $branchCount; ++$i){
				$branchX = $targetX;
				$branchZ = $targetZ;
				$face = $random->nextBoundedInt(4);
				match($face){
					0 => $branchX++,
					1 => $branchX--,
					2 => $branchZ++,
					default => $branchZ--
				};
				$branchY = $targetY + $height;

				if(
					abs($branchX - $sourceX) < $maxDistance &&
					abs($branchZ - $sourceZ) < $maxDistance &&
					$world->getBlockAt($branchX, $branchY, $branchZ)->getTypeId() === BlockTypeIds::AIR &&
					$world->getBlockAt($branchX, $branchY - 1, $branchZ)->getTypeId() === BlockTypeIds::AIR &&
					$this->canGrow($world, $branchX, $branchY, $branchZ, $face)
				){
					$branched = true;
					$world->setBlockAt($branchX, $branchY, $branchZ, $plant);
					$this->grow($world, $branchX, $branchY, $branchZ, $sourceX, $sourceY, $sourceZ, $maxDistance, $age + 1, $random);
				}
			}
		}

		if(!$branched){
			$world->setBlockAt($targetX, $targetY + $height, $targetZ, $flower);
		}
	}
}
