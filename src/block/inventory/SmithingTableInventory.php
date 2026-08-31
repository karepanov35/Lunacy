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
 * @author Karepanov, MaJiHoBou
 * @link https://github.com/karepanov35/Lunacy
 * @link https://github.com/MaJiHoBou999/Lunacy
 *
 *
 */

declare(strict_types=1);
namespace pocketmine\block\inventory;

use pocketmine\inventory\SimpleInventory;
use pocketmine\inventory\TemporaryInventory;
use pocketmine\world\Position;

final class SmithingTableInventory extends SimpleInventory implements BlockInventory, TemporaryInventory{
	use BlockInventoryTrait;

	public const SLOT_INPUT = 0;
	public const SLOT_MATERIAL = 1;
	public const SLOT_TEMPLATE = 2;

	public function __construct(Position $holder){
		$this->holder = $holder;
		parent::__construct(3);
	}

	public function getInput() : Item{
		return $this->getItem(self::SLOT_INPUT);
	}

	public function getAddition() : Item{
		return $this->getItem(self::SLOT_MATERIAL);
	}

	public function getTemplate() : Item{
		return $this->getItem(self::SLOT_TEMPLATE);
	}
}
