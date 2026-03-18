<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\decorator;

use pocketmine\block\VanillaBlocks;
use pocketmine\block\Water;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

class SeagrassDecorator extends \pocketmine\world\generator\Decorator{

	public function decorate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$x = ($chunk_x << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16);
		$z = ($chunk_z << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16);
		$highest = $chunk->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
		for($y = $highest; $y >= World::Y_MIN + 1; --$y){
			$block = $world->getBlockAt($x, $y, $z);
			$below = $world->getBlockAt($x, $y - 1, $z);
			if($block instanceof Water && $below->isSolid()){
				$world->setBlockAt($x, $y, $z, VanillaBlocks::SEAGRASS());
				return;
			}
		}
	}
}
