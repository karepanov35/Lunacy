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
namespace pocketmine\inventory;

use pocketmine\item\Item;
use pocketmine\lang\Translatable;
use function strlen;

/**
 * Info for an item group in the creative inventory menu.
 */
final class CreativeGroup{
	/**
	 * @param Translatable|string $name Tooltip shown to the player on hover
	 * @param Item                $icon Item shown when the group is collapsed
	 */
	public function __construct(
		private readonly Translatable|string $name,
		private readonly Item $icon
	){
		$nameLength = $name instanceof Translatable ? strlen($name->getText()) : strlen($name);
		if($nameLength === 0){
			throw new \InvalidArgumentException("Creative group name cannot be empty");
		}
	}

	public function getName() : Translatable|string{ return $this->name; }

	public function getIcon() : Item{ return clone $this->icon; }
}
