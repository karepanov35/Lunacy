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

/**
 * Tags used by items to determine their cooldown group.
 *
 * These tag values are not related to Minecraft internal IDs.
 * They only share a visual similarity because these are the most obvious values to use.
 * Any arbitrary string can be used.
 *
 * @see Item::getCooldownTag()
 */
final class ItemCooldownTags{

	private function __construct(){
		//NOOP
	}

	public const CHORUS_FRUIT = "chorus_fruit";
	public const ENDER_PEARL = "ender_pearl";
	public const SHIELD = "shield";
	public const GOAT_HORN = "goat_horn";
}
