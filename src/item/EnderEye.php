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
use pocketmine\entity\projectile\EyeOfEnderSignal;
use pocketmine\entity\projectile\Throwable;
use pocketmine\player\Player;

class EnderEye extends ProjectileItem{

	public function getMaxStackSize() : int{
		return 64;
	}

	protected function createEntity(Location $location, Player $thrower) : Throwable{
		return new EyeOfEnderSignal($location, $thrower);
	}

	public function getThrowForce() : float{
		return 0.55;
	}

	public function getCooldownTicks() : int{
		return 0;
	}

	public function getCooldownTag() : ?string{
		return null;
	}
}
