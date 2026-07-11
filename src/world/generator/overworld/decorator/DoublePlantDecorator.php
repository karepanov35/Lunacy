<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\decorator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\DoublePlant;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Decorator;
use pocketmine\world\generator\object\DoubleTallPlant;
use pocketmine\world\generator\overworld\decorator\types\DoublePlantDecoration;
use pocketmine\world\generator\utils\SurfacePlacementUtils;

class DoublePlantDecorator extends Decorator{

	/**
	 * @param DoublePlantDecoration[] $decorations
	 */
	private static function getRandomDoublePlant(Random $random, array $decorations) : ?DoublePlant{
		$totalWeight = 0;
		foreach($decorations as $decoration){
			$totalWeight += $decoration->weight;
		}
		if($totalWeight <= 0){
			return null;
		}
		$weight = $random->nextBoundedInt($totalWeight);
		foreach($decorations as $decoration){
			$weight -= $decoration->weight;
			if($weight < 0){
				return $decoration->block;
			}
		}
		return null;
	}

	/** @var DoublePlantDecoration[] */
	private array $doublePlants = [];

	final public function setDoublePlants(DoublePlantDecoration ...$doublePlants) : void{
		$this->doublePlants = $doublePlants;
	}

	public function decorate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$x = $random->nextBoundedInt(16);
		$z = $random->nextBoundedInt(16);
		$worldX = ($chunk_x << Chunk::COORD_BIT_SIZE) + $x;
		$worldZ = ($chunk_z << Chunk::COORD_BIT_SIZE) + $z;
		$source_y = SurfacePlacementUtils::getSurfaceYForSoil($world, $worldX, $worldZ, BlockTypeIds::GRASS);
		if($source_y === null){
			return;
		}

		$species = self::getRandomDoublePlant($random, $this->doublePlants);
		if($species === null){
			return;
		}
		(new DoubleTallPlant($species))->generate($world, $random, $worldX, $source_y, $worldZ);
	}
}
