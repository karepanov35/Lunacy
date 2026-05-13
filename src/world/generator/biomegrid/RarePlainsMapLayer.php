<?php

declare(strict_types=1);

namespace pocketmine\world\generator\biomegrid;

use pocketmine\world\generator\overworld\biome\BiomeIds;

/**
 * Редкие варианты равнин и леса: sunflower plains из лугов и cherry grove из «зелёных» биомов.
 */
class RarePlainsMapLayer extends MapLayer{

	private MapLayer $below_layer;

	public function __construct(int $seed, MapLayer $below_layer){
		parent::__construct($seed);
		$this->below_layer = $below_layer;
	}

	public function generateValues(int $x, int $z, int $size_x, int $size_z) : array{
		$grid_x = $x - 1;
		$grid_z = $z - 1;
		$grid_size_x = $size_x + 2;
		$grid_size_z = $size_z + 2;

		$values = $this->below_layer->generateValues($grid_x, $grid_z, $grid_size_x, $grid_size_z);

		$final_values = [];
		for($i = 0; $i < $size_z; ++$i){
			for($j = 0; $j < $size_x; ++$j){
				$this->setCoordsSeed($x + $j, $z + $i);
				$center_value = $values[$j + 1 + ($i + 1) * $grid_size_x];

				if(($center_value === BiomeIds::FOREST ||
						$center_value === BiomeIds::FOREST_HILLS ||
						$center_value === BiomeIds::FLOWER_FOREST ||
						$center_value === BiomeIds::BIRCH_FOREST ||
						$center_value === BiomeIds::BIRCH_FOREST_HILLS) &&
					$this->nextInt(12) === 0
				){
					$center_value = BiomeIds::CHERRY_GROVE;
				}elseif($center_value === BiomeIds::PLAINS && $this->nextInt(57) === 0){
					// Как старый ванильный sunflower patch на равнинах
					$center_value = BiomeIds::SUNFLOWER_PLAINS;
				}

				$final_values[$j + $i * $size_x] = $center_value;
			}
		}

		return $final_values;
	}
}
