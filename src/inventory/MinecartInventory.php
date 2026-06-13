<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\inventory;

use pocketmine\entity\Entity;

/**
 * Базовый инвентарь вагонетки.
 * Базовый инвентарь сущности-вагонетки; конкретные типы помечаются
 * MinecartChestInventoryInterface / HopperMinecartInventoryInterface.
 */
abstract class MinecartInventory extends SimpleInventory implements VirtualContainerInventory{

	private Entity $entity;

	public function __construct(Entity $entity, int $size){
		parent::__construct($size);
		$this->entity = $entity;
	}

	public function getEntityId() : int{
		return $this->entity->getId();
	}

	public function getHolder() : Entity{
		return $this->entity;
	}
}
