<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\decorator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Decorator;
use pocketmine\world\generator\object\TallGrass;
use pocketmine\world\generator\utils\SurfacePlacementUtils;

class TallGrassDecorator extends Decorator{

	private float $fern_density = 0.0;

	final public function setFernDensity(float $fern_density) : void{
		$this->fern_density = $fern_density;
	}

	public function decorate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$x = $random->nextBoundedInt(16);
		$z = $random->nextBoundedInt(16);
		$worldX = ($chunk_x << Chunk::COORD_BIT_SIZE) + $x;
		$worldZ = ($chunk_z << Chunk::COORD_BIT_SIZE) + $z;
		$source_y = SurfacePlacementUtils::getSurfaceYForSoil($world, $worldX, $worldZ, BlockTypeIds::GRASS, BlockTypeIds::DIRT);
		if($source_y === null){
			return;
		}

		(new TallGrass($this->fern_density > 0 && $random->nextFloat() < $this->fern_density ?
			VanillaBlocks::FERN() :
			VanillaBlocks::TALL_GRASS()
		))->generate($world, $random, $worldX, $source_y, $worldZ);
	}
}
