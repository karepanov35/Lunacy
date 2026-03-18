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

use pocketmine\world\generator\populator\DesertPlant;

class DesertBiome extends SandyBiome{

	public function __construct(){
		parent::__construct();
		
		// Mountains in desert - higher elevation with more variation
		$this->setElevation(63, 85);

		// Add desert plants (cactus and dead bush)
		$desertPlants = new DesertPlant();
		$desertPlants->setBaseAmount(2);
		$desertPlants->setRandomAmount(4);
		$this->addPopulator($desertPlants);

		$this->temperature = 2;
		$this->rainfall = 0;
	}

	public function getName() : string{
		return "Desert";
	}
}
