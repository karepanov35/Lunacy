<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator\lumi;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\generator\VanillaPopulator;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/** Порт {@code BasaltDeltaMagmaPopulator} (Lumi). */
final class BasaltDeltaMagmaPopulator implements VanillaPopulator{

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$amount = $random->nextBoundedInt(4) + 20;
		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;

		for($i = 0; $i < $amount; ++$i){
			$x = $cx + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
			$z = $cz + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
			foreach($this->getHighestWorkableBlocks($world, $x, $z) as $y){
				if($y <= 1){
					continue;
				}
				$world->setBlockAt($x, $y, $z, VanillaBlocks::MAGMA());
			}
		}
	}

	/**
	 * @return int[]
	 */
	private function getHighestWorkableBlocks(ChunkManager $world, int $x, int $z) : array{
		$ys = [];
		for($y = $world->getMaxY() - 1; $y > 0; --$y){
			$b = $world->getBlockAt($x, $y, $z)->getTypeId();
			if($b !== BlockTypeIds::BASALT && $b !== BlockTypeIds::BLACKSTONE){
				continue;
			}
			if($world->getBlockAt($x, $y + 1, $z)->getTypeId() !== BlockTypeIds::AIR){
				continue;
			}
			$b1 = $world->getBlockAt($x + 1, $y, $z)->getTypeId();
			$b2 = $world->getBlockAt($x - 1, $y, $z)->getTypeId();
			$b3 = $world->getBlockAt($x, $y, $z + 1)->getTypeId();
			$b4 = $world->getBlockAt($x, $y, $z - 1)->getTypeId();
			$lava = BlockTypeIds::LAVA;
			$stillLava = VanillaBlocks::LAVA()->getStillForm()->getTypeId();
			$isLava = static fn(int $id) : bool => $id === $lava || $id === $stillLava;
			if($isLava($b1) || $isLava($b2) || $isLava($b3) || $isLava($b4)){
				$ys[] = $y;
			}
		}
		return $ys;
	}
}
