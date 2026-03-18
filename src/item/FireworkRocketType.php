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

use pocketmine\world\sound\FireworkExplosionSound;
use pocketmine\world\sound\FireworkLargeExplosionSound;
use pocketmine\world\sound\Sound;

enum FireworkRocketType{
	case SMALL_BALL;
	case LARGE_BALL;
	case STAR;
	case CREEPER;
	case BURST;

	public function getExplosionSound() : Sound{
		return match($this){
			self::SMALL_BALL,
			self::STAR,
			self::CREEPER,
			self::BURST => new FireworkExplosionSound(),
			self::LARGE_BALL => new FireworkLargeExplosionSound(),
		};
	}
}
