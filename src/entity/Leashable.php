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

namespace pocketmine\entity;

use pocketmine\player\Player;

interface Leashable{

	public const LEASH_INTERACT_NONE = 0;
	public const LEASH_INTERACT_ATTACHED = 1;
	public const LEASH_INTERACT_DETACHED = 2;

	public const LEASH_TAG_UUID = "LeashUUID";
	public const LEASH_MAX_DISTANCE_SQ = 100.0;
	public const LEASH_MIN_DISTANCE = 3.0;
	public const LEASH_FOLLOW_OFFSET = 4.5;

	public function toggleLeashWithLead(Player $player) : int;

	public function isLeashed() : bool;
}
