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
namespace pocketmine\event\world;

use pocketmine\world\World;

/**
 * Called when a world's difficulty is changed.
 */
final class WorldDifficultyChangeEvent extends WorldEvent{

	public function __construct(
		World $world,
		private int $oldDifficulty,
		private int $newDifficulty
	){
		parent::__construct($world);
	}

	public function getOldDifficulty() : int{ return $this->oldDifficulty; }

	public function getNewDifficulty() : int{ return $this->newDifficulty; }
}
