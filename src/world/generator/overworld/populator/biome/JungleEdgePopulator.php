<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\populator\biome;

use pocketmine\world\generator\object\tree\BigOakTree;
use pocketmine\world\generator\object\tree\CocoaTree;
use pocketmine\world\generator\overworld\biome\BiomeIds;
use pocketmine\world\generator\overworld\decorator\types\TreeDecoration;

class JungleEdgePopulator extends JunglePopulator{

	/** @var TreeDecoration[] */
	protected static array $TREES;

	protected static function initTrees() : void{
		self::$TREES = [
			new TreeDecoration(BigOakTree::class, 10),
			new TreeDecoration(CocoaTree::class, 45)
		];
	}

	protected function initPopulators() : void{
		$this->tree_decorator->setAmount(2);
		$this->tree_decorator->setTrees(...self::$TREES);
	}

	public function getBiomes() : ?array{
		return [BiomeIds::JUNGLE_EDGE, BiomeIds::JUNGLE_EDGE_MUTATED];
	}
}
JungleEdgePopulator::init();