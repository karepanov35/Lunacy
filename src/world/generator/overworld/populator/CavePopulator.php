<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\populator;

use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\VanillaPopulator;
use pocketmine\world\generator\populator\Cave;

class CavePopulator implements VanillaPopulator{

	private Cave $cave;

	public function __construct(int $worldSeed){
		$this->cave = new Cave($worldSeed);
	}

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$this->cave->populate($world, $chunk_x, $chunk_z, $random);
	}
}
