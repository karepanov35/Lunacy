<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\utils\SurfacePlacementUtils;

class TallGrass extends TerrainObject{

	private const ATTEMPTS = 8;

	public function __construct(
		private Block $grass_type
	){}

	public function generate(ChunkManager $world, Random $random, int $source_x, int $source_y, int $source_z) : bool{
		$succeeded = false;
		for($i = 0; $i < self::ATTEMPTS; ++$i){
			$x = $source_x + $random->nextBoundedInt(8) - $random->nextBoundedInt(8);
			$z = $source_z + $random->nextBoundedInt(8) - $random->nextBoundedInt(8);
			$y = SurfacePlacementUtils::getSurfaceYForSoil($world, $x, $z, BlockTypeIds::GRASS, BlockTypeIds::DIRT);
			if($y === null){
				continue;
			}
			if(!$world->getBlockAt($x, $y - 2, $z)->isSolid()){
				continue;
			}
			$above = $world->getBlockAt($x, $y, $z);
			if($above->getTypeId() !== BlockTypeIds::AIR && $above->getTypeId() !== BlockTypeIds::SNOW_LAYER && !($above instanceof Leaves)){
				if($above->isSolid() || !$above->canBeReplaced()){
					continue;
				}
			}
			$world->setBlockAt($x, $y, $z, $this->grass_type);
			$succeeded = true;
		}
		return $succeeded;
	}
}
