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
namespace pocketmine\event\entity;

use pocketmine\entity\object\ItemEntity;

/**
 * Called when an item is spawned or loaded.
 *
 * Some possible reasons include:
 * - item is loaded from disk
 * - player dropping an item
 * - block drops
 * - loot of a player or entity
 *
 * @see PlayerDropItemEvent
 * @phpstan-extends EntityEvent<ItemEntity>
 */
class ItemSpawnEvent extends EntityEvent{

	public function __construct(ItemEntity $item){
		$this->entity = $item;

	}

	/**
	 * @return ItemEntity
	 */
	public function getEntity(){
		return $this->entity;
	}
}
