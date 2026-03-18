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

use pocketmine\entity\Living;

class SlownessEffect extends Effect{

	public function add(Living $entity, EffectInstance $instance) : void{
		$entity->setMovementSpeed($entity->getMovementSpeed() * (1 - 0.15 * $instance->getEffectLevel()), true);
	}

	public function remove(Living $entity, EffectInstance $instance) : void{
		$entity->setMovementSpeed($entity->getMovementSpeed() / (1 - 0.15 * $instance->getEffectLevel()));
	}
}
