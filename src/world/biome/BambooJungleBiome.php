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

use pocketmine\world\generator\populator\BambooPopulator;
use pocketmine\world\generator\populator\JungleBigTreePopulator;
use pocketmine\world\generator\populator\Melon;

class BambooJungleBiome extends JungleBiome{

	public function __construct(){
		parent::__construct();
		$this->clearPopulators();

		$bamboo = new BambooPopulator();
		$bamboo->setBaseAmount(80);
		$bamboo->setRandomAmount(30);
		$this->addPopulator($bamboo);

		$bigTrees = new JungleBigTreePopulator();
		$bigTrees->setBaseAmount(-1);
		$bigTrees->setRandomAmount(2);
		$this->addPopulator($bigTrees);

		$melon = new Melon();
		$melon->setRandomAmount(2);
		$this->addPopulator($melon);

		$this->setElevation(64, 80);
	}

	public function getName() : string{
		return "Bamboo Jungle";
	}
}
