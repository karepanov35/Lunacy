<?php

declare(strict_types=1);

namespace pocketmine\world\generator\biomegrid\utils;

use pocketmine\world\generator\biomegrid\MapLayer;

final class MapLayerPair{

	public function __construct(
		public MapLayer $high_resolution,
		public ?MapLayer $low_resolution
	){}
}