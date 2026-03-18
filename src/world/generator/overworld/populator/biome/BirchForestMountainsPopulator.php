<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\populator\biome;

use pocketmine\world\generator\object\tree\BirchTree;
use pocketmine\world\generator\object\tree\TallBirchTree;
use pocketmine\world\generator\overworld\biome\BiomeIds;
use pocketmine\world\generator\overworld\decorator\types\TreeDecoration;

class BirchForestMountainsPopulator extends ForestPopulator{

	private const BIOMES = [BiomeIds::BIRCH_FOREST_MUTATED, BiomeIds::BIRCH_FOREST_HILLS_MUTATED];

	/** @var TreeDecoration[] */
	protected static array $TREES;

	protected static function initTrees() : void{
		self::$TREES = [
			new TreeDecoration(BirchTree::class, 1),
			new TreeDecoration(TallBirchTree::class, 1)
		];
	}

	protected function initPopulators() : void{
		$this->tree_decorator->setTrees(...self::$TREES);
	}

	public function getBiomes() : ?array{
		return self::BIOMES;
	}
}

BirchForestMountainsPopulator::init();