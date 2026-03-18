<?php

declare(strict_types=1);

namespace pocketmine\world\generator;

use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/**
 * VanillaGenerator populator interface (different from PMMP5 Populator)
 */
interface VanillaPopulator{

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void;
}
