<?php

declare(strict_types=1);

namespace pocketmine\world\generator\utils;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\Liquid;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

final class SurfacePlacementUtils{

	private function __construct(){
	}

	/**
	 * Returns the Y of the first empty block sitting on solid ground in the column.
	 */
	public static function getSurfaceY(ChunkManager $world, int $x, int $z) : ?int{
		$chunk = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE);
		if($chunk === null){
			return null;
		}

		$highestBlock = $chunk->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
		$minY = $world->getMinY();

		for($y = $highestBlock; $y >= $minY; --$y){
			$block = $world->getBlockAt($x, $y, $z);
			$typeId = $block->getTypeId();
			if(
				$typeId === BlockTypeIds::AIR ||
				$typeId === BlockTypeIds::SNOW_LAYER ||
				$block instanceof Leaves ||
				!$block->isSolid()
			){
				continue;
			}

			$above = $world->getBlockAt($x, $y + 1, $z);
			$aboveType = $above->getTypeId();
			if($aboveType === BlockTypeIds::AIR || $aboveType === BlockTypeIds::SNOW_LAYER){
				return $y + 1;
			}
			if(!$above->isSolid() && $above->canBeReplaced() && !($above instanceof Liquid)){
				return $y + 1;
			}
		}

		return null;
	}

	/**
	 * Returns surface Y only when the supporting block matches one of the given soil type IDs.
	 *
	 * @param int ...$validSoilTypes
	 */
	public static function getSurfaceYForSoil(ChunkManager $world, int $x, int $z, int ...$validSoilTypes) : ?int{
		$surfaceY = self::getSurfaceY($world, $x, $z);
		if($surfaceY === null){
			return null;
		}

		$belowType = $world->getBlockAt($x, $surfaceY - 1, $z)->getTypeId();
		foreach($validSoilTypes as $soilType){
			if($belowType === $soilType){
				return $surfaceY;
			}
		}

		return null;
	}
}
