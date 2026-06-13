<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\inventory;

use pocketmine\entity\HopperMinecart;
use pocketmine\inventory\HopperMinecartInventoryInterface;

/**
 * 5-слотовый инвентарь вагонетки с воронкой.
 */
class MinecartHopperInventory extends MinecartInventory implements HopperMinecartInventoryInterface{

	public function __construct(HopperMinecart $entity){
		parent::__construct($entity, 5);
	}
}
