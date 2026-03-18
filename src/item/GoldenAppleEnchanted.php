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

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;

class GoldenAppleEnchanted extends GoldenApple{

	public function getAdditionalEffects() : array{
		return [
			new EffectInstance(VanillaEffects::REGENERATION(), 600, 1),
			new EffectInstance(VanillaEffects::ABSORPTION(), 2400, 3),
			new EffectInstance(VanillaEffects::RESISTANCE(), 6000),
			new EffectInstance(VanillaEffects::FIRE_RESISTANCE(), 6000)
		];
	}
}
