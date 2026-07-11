<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\DoublePlant;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\utils\SurfacePlacementUtils;

class DoubleTallPlant extends TerrainObject{

	private const ATTEMPTS = 4;

	public function __construct(
		private DoublePlant $species
	){}

	public function generate(ChunkManager $world, Random $random, int $source_x, int $source_y, int $source_z) : bool{
		$placed = false;
		for($i = 0; $i < self::ATTEMPTS; ++$i){
			$x = $source_x + $random->nextBoundedInt(8) - $random->nextBoundedInt(8);
			$z = $source_z + $random->nextBoundedInt(8) - $random->nextBoundedInt(8);
			$y = SurfacePlacementUtils::getSurfaceYForSoil($world, $x, $z, BlockTypeIds::GRASS);
			if($y === null || $y + 1 >= $world->getMaxY()){
				continue;
			}
			if(!$world->getBlockAt($x, $y - 2, $z)->isSolid()){
				continue;
			}
			if(
				$world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::AIR ||
				$world->getBlockAt($x, $y + 1, $z)->getTypeId() !== BlockTypeIds::AIR
			){
				continue;
			}
			$world->setBlockAt($x, $y, $z, $this->species->setTop(false));
			$world->setBlockAt($x, $y + 1, $z, $this->species->setTop(true));
			$placed = true;
		}

		return $placed;
	}
}
