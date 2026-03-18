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
/**
 * Entity related Events, like spawn, inventory, attack...
 */
namespace pocketmine\event\entity;

use pocketmine\entity\Entity;
use pocketmine\event\Event;

/**
 * @phpstan-template TEntity of Entity
 */
abstract class EntityEvent extends Event{
	/** @phpstan-var TEntity */
	protected Entity $entity;

	/**
	 * @return Entity
	 * @phpstan-return TEntity
	 */
	public function getEntity(){
		return $this->entity;
	}
}
