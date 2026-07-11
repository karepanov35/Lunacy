<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\decorator;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Decorator;
use pocketmine\world\generator\object\Flower;
use pocketmine\world\generator\overworld\decorator\types\FlowerDecoration;
use pocketmine\world\generator\utils\SurfacePlacementUtils;

class FlowerDecorator extends Decorator{

	/**
	 * @param FlowerDecoration[] $decorations
	 */
	private static function getRandomFlower(Random $random, array $decorations) : ?Block{
		$total_weight = 0;
		foreach($decorations as $decoration){
			$total_weight += $decoration->weight;
		}

		if($total_weight > 0){
			$weight = $random->nextBoundedInt($total_weight);
			foreach($decorations as $decoration){
				$weight -= $decoration->weight;
				if($weight < 0){
					return $decoration->block;
				}
			}
		}

		return null;
	}

	/** @var FlowerDecoration[] */
	private array $flowers = [];

	final public function setFlowers(FlowerDecoration ...$flowers) : void{
		$this->flowers = $flowers;
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

		$flower = self::getRandomFlower($random, $this->flowers);
		if($flower !== null){
			(new Flower($flower))->generate($world, $random, $worldX, $source_y, $worldZ);
		}
	}
}
