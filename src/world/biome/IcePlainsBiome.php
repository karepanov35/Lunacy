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

class IcePlainsBiome extends SnowyBiome{

	public function __construct(){
		parent::__construct();

		$tallGrass = new TallGrass();
		$tallGrass->setBaseAmount(5);
		$this->addPopulator($tallGrass);

		// Add spruce trees
		$trees = new Tree(TreeType::SPRUCE);
		$trees->setBaseAmount(1);
		$trees->setRandomAmount(2);
		$this->addPopulator($trees);

		// Higher elevation
		$this->setElevation(65, 82);

		$this->temperature = 0.05;
		$this->rainfall = 0.8;
	}

	public function getName() : string{
		return "Ice Plains";
	}
}
