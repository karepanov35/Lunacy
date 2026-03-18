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
namespace pocketmine\item;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Egg as EggEntity;
use pocketmine\entity\projectile\Throwable;
use pocketmine\player\Player;

class Egg extends ProjectileItem{

	public function getMaxStackSize() : int{
		return 16;
	}

	protected function createEntity(Location $location, Player $thrower) : Throwable{
		return new EggEntity($location, $thrower);
	}

	public function getThrowForce() : float{
		return 1.5;
	}
}
