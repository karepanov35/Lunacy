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

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\Sound;
use pocketmine\world\World;

/**
 * Called when a sound is played in a world
 * @see World::addSound()
 */
class WorldSoundEvent extends WorldEvent implements Cancellable{
	use CancellableTrait;

	/**
	 * @param Player[] $recipients
	 */
	public function __construct(
		World $world,
		private Sound $sound,
		private Vector3 $position,
		private array $recipients
	){
		parent::__construct($world);
	}

	public function getSound() : Sound{
		return $this->sound;
	}

	public function setSound(Sound $sound) : void{
		$this->sound = $sound;
	}

	public function getPosition() : Vector3{
		return $this->position;
	}

	/**
	 * @return Player[]
	 */
	public function getRecipients() : array{
		return $this->recipients;
	}

	/**
	 * @param Player[] $recipients
	 */
	public function setRecipients(array $recipients) : void{
		$this->recipients = $recipients;
	}
}
