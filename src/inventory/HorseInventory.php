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

use pocketmine\entity\Horse;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

/**
 * Инвентарь лошади: слот 0 = седло, слот 1 = броня.
 * Реализует HorseInventoryInterface чтобы InventoryManager мог открыть
 * правильный тип окна (WindowTypes::HORSE).
 */
class HorseInventory extends SimpleInventory implements HorseInventoryInterface{

	public const SLOT_SADDLE = 0;
	public const SLOT_ARMOR  = 1;

	public function __construct(private Horse $horse){
		parent::__construct(2);
	}

	public function getHorse() : Horse{
		return $this->horse;
	}

	/** Возвращает entity ID лошади для ContainerOpenPacket::entityInv() */
	public function getEntityId() : int{
		return $this->horse->getId();
	}

	public function getSaddle() : Item{
		return $this->getItem(self::SLOT_SADDLE);
	}

	public function getArmor() : Item{
		return $this->getItem(self::SLOT_ARMOR);
	}

	public function isValidSlot(int $slot) : bool{
		return $slot === self::SLOT_SADDLE || $slot === self::SLOT_ARMOR;
	}

	public function canAddItem(Item $item) : bool{
		return true;
	}
}
