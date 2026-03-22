<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator\lumi;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\generator\VanillaPopulator;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/** Порт {@code PopulatorSoulsandFossils} (Lumi). */
final class SoulsandFossilPopulator implements VanillaPopulator{

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		if($random->nextBoundedInt(5) !== 0){
			return;
		}
		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;
		$x = $cx + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
		$z = $cz + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
		$lx = $x & Chunk::COORD_MASK;
		$lz = $z & Chunk::COORD_MASK;
		$y = $this->getHighestWorkableBlock($chunk, $lx, $lz);
		if($y === -1 || $world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::NETHERRACK){
			return;
		}

		$count = $random->nextBoundedInt(11) + 10; // 10–20
		for($i = 0; $i < $count; ++$i){
			$world->setBlockAt(
				$x + $random->nextBoundedInt(6) - 3,
				$y + $random->nextBoundedInt(3),
				$z + $random->nextBoundedInt(6) - 3,
				VanillaBlocks::BONE_BLOCK()
			);
		}
	}

	private function getHighestWorkableBlock(Chunk $chunk, int $lx, int $lz) : int{
		for($y = 120; $y >= 0; --$y){
			$b = $chunk->getBlockStateId($lx, $y, $lz);
			if($b === VanillaBlocks::SOUL_SAND()->getStateId() || $b === VanillaBlocks::SOUL_SOIL()->getStateId()){
				return $y === 0 ? -1 : $y;
			}
		}
		return -1;
	}
}
