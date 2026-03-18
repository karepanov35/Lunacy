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
namespace pocketmine\world\generator\populator;

use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\object\Ore as ObjectOre;
use pocketmine\world\generator\object\OreType;

class Ore implements Populator{
	/** @var OreType[] */
	private array $oreTypes = [];

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		foreach($this->oreTypes as $type){
			$ore = new ObjectOre($random, $type);
			// Generate ore veins - use amount as cluster count
			for($i = 0; $i < $type->amount; ++$i){
				$x = $random->nextRange($chunkX << Chunk::COORD_BIT_SIZE, ($chunkX << Chunk::COORD_BIT_SIZE) + Chunk::EDGE_LENGTH - 1);
				$y = $type->getRandomHeight($random);
				$z = $random->nextRange($chunkZ << Chunk::COORD_BIT_SIZE, ($chunkZ << Chunk::COORD_BIT_SIZE) + Chunk::EDGE_LENGTH - 1);
				if($ore->canPlaceObject($world, $x, $y, $z)){
					$ore->placeObject($world, $x, $y, $z);
				}
			}
		}
	}

	/**
	 * @param OreType[] $types
	 */
	public function setOreTypes(array $types) : void{
		$this->oreTypes = $types;
	}
}
