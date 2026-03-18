<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\populator\biome;

use pocketmine\block\VanillaBlocks;
use pocketmine\world\generator\overworld\biome\BiomeIds;

class OceanPopulator extends BiomePopulator{

	public function __construct(){
		parent::__construct();
	}

	protected function initPopulators() : void{
		$this->water_lake_decorator->setAmount(0);
		$this->lava_lake_decorator->setAmount(0);
		$this->surface_cave_decorator->setAmount(1);
		$this->sand_patch_decorator->setAmount(3);
		$this->sand_patch_decorator->setRadii(7, 2);
		$this->sand_patch_decorator->setOverridableBlocks(VanillaBlocks::DIRT(), VanillaBlocks::GRASS());
		$this->clay_patch_decorator->setAmount(1);
		$this->clay_patch_decorator->setRadii(4, 1);
		$this->clay_patch_decorator->setOverridableBlocks(VanillaBlocks::DIRT());
		$this->gravel_patch_decorator->setAmount(2);
		$this->gravel_patch_decorator->setRadii(6, 2);
		$this->gravel_patch_decorator->setOverridableBlocks(VanillaBlocks::DIRT(), VanillaBlocks::GRASS());

		$this->double_plant_decorator->setAmount(0);
		$this->tree_decorator->setAmount(0);
		$this->flower_decorator->setAmount(0);
		$this->tall_grass_decorator->setAmount(0);
		$this->dead_bush_decorator->setAmount(0);
		$this->brown_mushroom_decorator->setAmount(0);
		$this->red_mushroom_decorator->setAmount(0);
		$this->sugar_cane_decorator->setAmount(0);
		$this->cactus_decorator->setAmount(0);
	}

	public function getBiomes() : ?array{
		return [BiomeIds::OCEAN, BiomeIds::RIVER, BiomeIds::DEEP_OCEAN];
	}
}
