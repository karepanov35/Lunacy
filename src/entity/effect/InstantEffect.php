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
namespace pocketmine\entity\effect;

use pocketmine\color\Color;
use pocketmine\lang\Translatable;

abstract class InstantEffect extends Effect{

	public function __construct(Translatable|string $name, Color $color, bool $bad = false, bool $hasBubbles = true){
		parent::__construct($name, $color, $bad, 1, $hasBubbles);
	}

	public function getApplyInterval(EffectInstance $instance) : int{
		return 1; //If forced to last longer than 1 tick, these apply every tick.
	}
}
