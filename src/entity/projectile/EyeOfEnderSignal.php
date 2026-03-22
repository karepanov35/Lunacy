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

namespace pocketmine\entity\projectile;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\particle\PortalParticle;
use function cos;
use function sin;

class EyeOfEnderSignal extends Throwable{

	private Vector3 $homingTarget;

	public static function getNetworkTypeId() : string{
		return "minecraft:eye_of_ender_signal";
	}

	public function __construct(Location $location, ?Entity $shootingEntity, ?CompoundTag $nbt = null){
		parent::__construct($location, $shootingEntity, $nbt);
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.25, 0.25);
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setBaseDamage(0.0);
		$this->setCanSaveWithChunk(false);

		$world = $this->getWorld();
		$spawn = $world->getSpawnLocation();
		$seed = abs((int) ($spawn->x * 31 + $spawn->z * 17)) ^ (strlen($world->getFolderName()) * 131);
		$angle = (($seed % 628) / 100.0);
		$dist = 90 + ($seed % 140);
		$y = 28 + ($seed % 24);
		$this->homingTarget = new Vector3(
			$spawn->x + cos($angle) * $dist,
			$y,
			$spawn->z + sin($angle) * $dist
		);
	}

	public function canCollideWith(Entity $entity) : bool{
		return false;
	}

	protected function calculateInterceptWithBlock(Block $block, Vector3 $start, Vector3 $end) : ?RayTraceResult{
		return null;
	}

	protected function tryChangeMovement() : void{
		$to = $this->homingTarget->subtractVector($this->location->asVector3());
		$len = $to->length();
		if($len < 0.001 || $len < 2.0){
			$this->finishFlight();

			return;
		}
		$dir = $to->normalize();
		$speed = 0.5;
		$wobble = sin($this->ticksLived * 0.18) * 0.03;
		$this->motion = new Vector3(
			$dir->x * $speed,
			$dir->y * $speed + $wobble,
			$dir->z * $speed
		);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$has = parent::entityBaseTick($tickDiff);
		if($this->isAlive() && $this->ticksLived % 4 === 0){
			$this->getWorld()->addParticle($this->location->add(0, 0.1, 0), new PortalParticle());
		}
		if($this->isAlive() && $this->ticksLived > 400){
			$this->finishFlight();
		}

		return $has;
	}

	private function finishFlight() : void{
		$world = $this->getWorld();
		$pos = $this->location;
		for($i = 0; $i < 28; ++$i){
			$world->addParticle($pos, new PortalParticle());
		}
		$world->dropItem($pos, VanillaItems::ENDER_EYE());
		$this->flagForDespawn();
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		parent::onHit($event);
	}
}
