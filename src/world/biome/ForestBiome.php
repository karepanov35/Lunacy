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

class ForestBiome extends GrassyBiome{
	private TreeType $type;

	public function __construct(?TreeType $type = null){
		parent::__construct();

		$this->type = $type ?? TreeType::OAK;

		$trees = new Tree($type);
		$trees->setBaseAmount(10); // Increased from 5 for denser forests
		$this->addPopulator($trees);

		$tallGrass = new TallGrass();
		$tallGrass->setBaseAmount(10); // Увеличено с 3 до 10
		$tallGrass->setRandomAmount(10); // Добавлена случайность

		$this->addPopulator($tallGrass);

		// Higher forest with mountains
		$this->setElevation(68, 95);

		if($this->type === TreeType::BIRCH){
			$this->temperature = 0.6;
			$this->rainfall = 0.5;
		}else{
			$this->temperature = 0.7;
			$this->rainfall = 0.8;
		}
	}

	public function getName() : string{
		return $this->type->getDisplayName() . " Forest";
	}
}
