<?php


/*
 *
 *
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GPL-2.0 license as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Karepanov
 * @link https://github.com/karepanov35/Lunacy
 *
 *
 */

declare(strict_types=1);
namespace pocketmine\world\biome;

use pocketmine\block\VanillaBlocks;
use pocketmine\world\generator\populator\Seagrass;
use pocketmine\world\generator\populator\TallGrass;

class OceanBiome extends Biome{

	public function __construct(){
		$this->setGroundCover([
			VanillaBlocks::GRAVEL(),
			VanillaBlocks::GRAVEL(),
			VanillaBlocks::GRAVEL(),
			VanillaBlocks::GRAVEL(),
			VanillaBlocks::GRAVEL()
		]);

		$tallGrass = new TallGrass();
		$tallGrass->setBaseAmount(5);

		$this->addPopulator($tallGrass);

		$seagrass = new Seagrass();
		$seagrass->setBaseAmount(10);
		$seagrass->setRandomAmount(15);
		$this->addPopulator($seagrass);

		// Deeper ocean - from 40 to 55 (instead of 46-58)
		$this->setElevation(40, 55);

		$this->temperature = 0.5;
		$this->rainfall = 0.5;
	}

	public function getName() : string{
		return "Ocean";
	}
}
