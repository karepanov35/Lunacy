<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\utils\SurfacePlacementUtils;

class Flower extends TerrainObject{

	private const ATTEMPTS = 4;

	public function __construct(
		private Block $block
	){}

	public function generate(ChunkManager $world, Random $random, int $source_x, int $source_y, int $source_z) : bool{
		$succeeded = false;
		for($i = 0; $i < self::ATTEMPTS; ++$i){
			$x = $source_x + $random->nextBoundedInt(8) - $random->nextBoundedInt(8);
			$z = $source_z + $random->nextBoundedInt(8) - $random->nextBoundedInt(8);
			$y = SurfacePlacementUtils::getSurfaceYForSoil($world, $x, $z, BlockTypeIds::GRASS);
			if($y === null){
				continue;
			}
			if(!$world->getBlockAt($x, $y - 2, $z)->isSolid()){
				continue;
			}
			$here = $world->getBlockAt($x, $y, $z);
			if($here->getTypeId() !== BlockTypeIds::AIR && (!$here->canBeReplaced() || $here->isSolid())){
				continue;
			}
			$world->setBlockAt($x, $y, $z, $this->block);
			$succeeded = true;
		}

		return $succeeded;
	}
}
