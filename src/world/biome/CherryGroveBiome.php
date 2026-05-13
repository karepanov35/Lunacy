<?php

declare(strict_types=1);

namespace pocketmine\world\biome;

/**
 * Cherry Grove biome.
 * Tree generation is handled entirely by CherryGrovePopulator (overworld generator).
 * This class only provides ground cover, elevation, temperature and rainfall.
 */
class CherryGroveBiome extends GrassyBiome{

	public function __construct(){
		parent::__construct();

		$this->setElevation(63, 80);
		$this->temperature = 0.5;
		$this->rainfall = 0.8;
	}

	public function getName() : string{
		return "Cherry Grove";
	}
}
