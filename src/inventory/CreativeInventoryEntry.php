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

final class CreativeInventoryEntry{
	private readonly Item $item;

	public function __construct(
		Item $item,
		private readonly CreativeCategory $category,
		private readonly ?CreativeGroup $group = null
	){
		$this->item = clone $item;
	}

	public function getItem() : Item{ return clone $this->item; }

	public function getCategory() : CreativeCategory{ return $this->category; }

	public function getGroup() : ?CreativeGroup{ return $this->group; }

	public function matchesItem(Item $item) : bool{
		return $item->equals($this->item, checkDamage: true, checkCompound: false);
	}
}
