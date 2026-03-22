<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator\lumi;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\generator\VanillaPopulator;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/** Порт {@code PopulatorGlowStone} (Lumi). */
final class LumiGlowstonePopulator implements VanillaPopulator{

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		if($random->nextBoundedInt(10) !== 0){
			return;
		}
		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;
		$x = $cx + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
		$z = $cz + $random->nextBoundedInt(Chunk::EDGE_LENGTH);
		$y = $this->getHighestWorkableBlock($world, $x, $z);
		if($y === -1){
			return;
		}
		if($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::NETHERRACK){
			return;
		}

		$count = $random->nextBoundedInt(21) + 40; // 40–60
		$world->setBlockAt($x, $y, $z, VanillaBlocks::GLOWSTONE());
		$cyclesNum = 0;
		while($count !== 0){
			if($cyclesNum === 1500){
				break;
			}
			$spawnX = $x + $random->nextBoundedInt(8) - $random->nextBoundedInt(8);
			$spawnY = $y - $random->nextBoundedInt(5);
			$spawnZ = $z + $random->nextBoundedInt(8) - $random->nextBoundedInt(8);
			if($cyclesNum % 128 === 0 && $cyclesNum !== 0){
				$world->setBlockAt(
					$x + $random->nextBoundedInt(7) - 3,
					$y - $random->nextBoundedInt(4),
					$z + $random->nextBoundedInt(7) - 3,
					VanillaBlocks::GLOWSTONE()
				);
				$count--;
			}
			if($this->checkAroundBlock($spawnX, $spawnY, $spawnZ, $world)){
				$world->setBlockAt($spawnX, $spawnY, $spawnZ, VanillaBlocks::GLOWSTONE());
				$count--;
			}
			$cyclesNum++;
		}
	}

	private function getHighestWorkableBlock(ChunkManager $world, int $x, int $z) : int{
		for($y = 125; $y >= 0; --$y){
			if($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::AIR){
				return $y === 0 ? -1 : $y;
			}
		}
		return -1;
	}

	private function checkAroundBlock(int $x, int $y, int $z, ChunkManager $level) : bool{
		$vector = new Vector3($x, $y, $z);
		foreach(Facing::ALL as $face){
			$pos = $vector->getSide($face);
			if($level->getBlockAt($pos->x, $pos->y, $pos->z)->getTypeId() === BlockTypeIds::GLOWSTONE){
				return true;
			}
		}
		return false;
	}
}
