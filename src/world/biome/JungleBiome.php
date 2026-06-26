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
use pocketmine\world\generator\populator\BambooPopulator;
use pocketmine\world\generator\populator\Flower;
use pocketmine\world\generator\populator\JungleBigTreePopulator;
use pocketmine\world\generator\populator\Melon;
use pocketmine\world\generator\populator\Tree;

class JungleBiome extends GrassyBiome{

	public function __construct(){
		parent::__construct();

		$trees = new Tree(TreeType::JUNGLE);
		$trees->setBaseAmount(10);
		$this->addPopulator($trees);

		$bigTrees = new JungleBigTreePopulator();
		$bigTrees->setBaseAmount(7);
		$this->addPopulator($bigTrees);

		$melon = new Melon();
		$melon->setRandomAmount(2);
		$this->addPopulator($melon);

		$bamboo = new BambooPopulator();
		$bamboo->setRandomAmount(2);
		$this->addPopulator($bamboo);

		$flowers = new Flower();
		$flowers->setRandomAmount(3);
		$this->addPopulator($flowers);

		$this->setElevation(64, 80);

		$this->temperature = 0.95;
		$this->rainfall = 0.9;
	}

	public function getName() : string{
		return "Jungle";
	}
}
