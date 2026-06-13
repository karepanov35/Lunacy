<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\inventory;

/**
 * Маркерный интерфейс для инвентаря вагонетки с сундуком.
 * InventoryManager откроет WindowTypes::CONTAINER (как Nukkit InventoryType.MINECART_CHEST).
 */
interface MinecartChestInventoryInterface extends Inventory{

	public function getEntityId() : int;
}
