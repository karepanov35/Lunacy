<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\inventory;

/**
 * Маркерный интерфейс для инвентаря воронки-вагонетки.
 * InventoryManager откроет WindowTypes::HOPPER (как Nukkit InventoryType.MINECART_HOPPER).
 */
interface HopperMinecartInventoryInterface extends Inventory{

	public function getEntityId() : int;
}
