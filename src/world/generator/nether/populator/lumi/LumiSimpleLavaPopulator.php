<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator\lumi;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\generator\VanillaPopulator;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/**
 * Упрощённый аналог {@code PopulatorLava} (Lumi): без полного распространения лавы,
 * только случайные источники в верхних пустых колонках (без тяжёлой физики).
 */
final class LumiSimpleLavaPopulator implements VanillaPopulator{

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		if($random->nextBoundedInt(20) !== 0) { // 5%
			return;
		}
		$amount = $random->nextBoundedInt(3) + 1;
		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;

		for($i = 0; $i < $amount; ++$i){
			$lx = $random->nextBoundedInt(Chunk::EDGE_LENGTH);
			$lz = $random->nextBoundedInt(Chunk::EDGE_LENGTH);
			$y = $this->getHighestWorkableBlock($chunk, $lx, $lz);
			if($y === -1){
				continue;
			}
			$wx = $cx + $lx;
			$wz = $cz + $lz;
			if($world->getBlockAt($wx, $y, $wz)->getTypeId() !== BlockTypeIds::AIR){
				continue;
			}
			$world->setBlockAt($wx, $y, $wz, VanillaBlocks::LAVA()->getStillForm());
		}
	}

	private function getHighestWorkableBlock(Chunk $chunk, int $lx, int $lz) : int{
		$top = min(127, $chunk->getHeight() - 1);
		for($y = $top; $y >= 0; --$y){
			if($chunk->getBlockStateId($lx, $y, $lz) === VanillaBlocks::AIR()->getStateId()){
				return $y === 0 ? -1 : $y;
			}
		}
		return -1;
	}
}
