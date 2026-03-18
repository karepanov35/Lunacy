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

use pocketmine\entity\Entity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\math\RayTraceResult;

class ProjectileHitEntityEvent extends ProjectileHitEvent{
	public function __construct(
		Projectile $entity,
		RayTraceResult $rayTraceResult,
		private Entity $entityHit
	){
		parent::__construct($entity, $rayTraceResult);
	}

	/**
	 * Returns the Entity struck by the projectile.
	 */
	public function getEntityHit() : Entity{
		return $this->entityHit;
	}
}
