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
namespace pocketmine\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;

final class ArmorStandSound implements Sound{

	public function __construct(
		private ArmorStandSoundType $type
	){}

	public function encode(Vector3 $pos) : array{
		$event = match($this->type){
			ArmorStandSoundType::PLACE => LevelEvent::SOUND_ARMOR_STAND_PLACE,
			ArmorStandSoundType::BREAK => LevelEvent::SOUND_ARMOR_STAND_BREAK,
			ArmorStandSoundType::HIT => LevelEvent::SOUND_ARMOR_STAND_HIT,
			ArmorStandSoundType::FALL => LevelEvent::SOUND_ARMOR_STAND_FALL,
		};

		return [LevelEventPacket::create($event, 0, $pos)];
	}
}
