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

use pocketmine\world\generator\object\TreeType;
use pocketmine\world\generator\populator\TallGrass;
use pocketmine\world\generator\populator\Tree;
use pocketmine\world\generator\populator\Wheat;

class PlainBiome extends GrassyBiome{

	public function __construct(){
		parent::__construct();

		$tallGrass = new TallGrass();
		$tallGrass->setBaseAmount(40); // Увеличено с 20 до 40
		$tallGrass->setRandomAmount(20); // Добавлена случайность

		$this->addPopulator($tallGrass);

		// Add sparse trees to plains (like vanilla)
		$trees = new Tree(TreeType::OAK);
		$trees->setBaseAmount(0);
		$trees->setRandomAmount(1); // Very sparse - 0-1 trees per chunk
		$this->addPopulator($trees);

		// Add wheat patches (30% chance)
		$wheat = new Wheat();
		$wheat->setBaseAmount(1);
		$wheat->setRandomAmount(2);
		$this->addPopulator($wheat);

		// Higher plains with rolling hills
		$this->setElevation(67, 78);

		$this->temperature = 0.8;
		$this->rainfall = 0.4;
	}

	public function getName() : string{
		return "Plains";
	}
}
