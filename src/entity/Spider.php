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
namespace pocketmine\entity;

use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function atan2;
use function cos;
use function floor;
use function mt_rand;
use function rad2deg;
use function sin;
use function sqrt;

class Spider extends Living{

	private ?Player $target = null;
	private int $attackCooldown = 0;
	private int $idleTime = 0;
	private ?Vector3 $wanderTarget = null;
	private float $smoothMotionX = 0.0;
	private float $smoothMotionZ = 0.0;

	public static function getNetworkTypeId() : string{
		return EntityIds::SPIDER;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.4, 1.4);
	}

	public function getName() : string{
		return "Spider";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(16);
		$this->setHealth(16);
		$this->setStepHeight(1.0);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			return $hasUpdate;
		}

		$this->validateTarget();
		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}

		if($this->target === null){
			$this->findNearestPlayer();
			$this->wanderAI();
		}else{
			$this->combatAI();
		}

		// Не затирать knockback от удара игрока движением из AI
		if($this->attackTime <= 0){
			$this->motion->x = $this->smoothMotionX;
			$this->motion->z = $this->smoothMotionZ;
		}

		return true;
	}

	private function validateTarget() : void{
		if($this->target === null){
			return;
		}
		if(!$this->target->isAlive() || $this->target->isClosed() || $this->target->isCreative()){
			$this->target = null;
			$this->setTargetEntity(null);
			return;
		}
		if($this->location->distanceSquared($this->target->getPosition()) > 256){
			$this->target = null;
			$this->setTargetEntity(null);
		}
	}

	private function findNearestPlayer() : void{
		$nearest = null;
		$nearestDist = 256.0;
		foreach($this->getWorld()->getPlayers() as $player){
			if(!$player->isAlive() || $player->isCreative()){
				continue;
			}
			$dist = $this->location->distanceSquared($player->getPosition());
			if($dist < $nearestDist && $this->canSee($player)){
				$nearestDist = $dist;
				$nearest = $player;
			}
		}
		if($nearest !== null){
			$this->target = $nearest;
			$this->setTargetEntity($nearest);
		}
	}

	private function canSee(Entity $entity) : bool{
		$start = $this->getEyePos();
		$end = $entity->getEyePos();
		$dir = $end->subtractVector($start)->normalize();
		$distance = $start->distance($end);
		for($i = 0; $i < $distance; $i += 0.5){
			$pos = $start->addVector($dir->multiply($i));
			if($this->getWorld()->getBlockAt((int) $pos->x, (int) $pos->y, (int) $pos->z)->isSolid()){
				return false;
			}
		}
		return true;
	}

	private function combatAI() : void{
		$targetPos = $this->target->getPosition();
		$distSq = $this->location->distanceSquared($targetPos);
		$dist = sqrt($distSq);

		$this->lookAt($this->target->getEyePos());

		if($distSq <= 2.25){
			$this->smoothMotionX *= 0.6;
			$this->smoothMotionZ *= 0.6;
			$this->attackTarget();
			return;
		}

		$x = $targetPos->x - $this->location->x;
		$z = $targetPos->z - $this->location->z;
		$len = sqrt($x * $x + $z * $z);
		if($len > 0.01){
			$speed = 0.2;
			$this->smoothMotionX = $this->smoothMotionX * 0.7 + ($x / $len) * $speed * 0.3;
			$this->smoothMotionZ = $this->smoothMotionZ * 0.7 + ($z / $len) * $speed * 0.3;
			$this->location->yaw = rad2deg(atan2(-$x, $z));
		}
	}

	private function attackTarget() : void{
		if($this->attackCooldown > 0 || $this->target === null){
			return;
		}
		$this->broadcastAnimation(new ArmSwingAnimation($this));
		$ev = new EntityDamageByEntityEvent($this, $this->target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 2.0);
		$this->target->attack($ev);
		$this->attackCooldown = 20;
	}

	private function wanderAI() : void{
		$this->idleTime--;
		if($this->wanderTarget !== null && $this->location->distanceSquared($this->wanderTarget) < 4){
			$this->wanderTarget = null;
		}
		if($this->wanderTarget === null && $this->idleTime <= 0){
			$this->idleTime = 60;
			$angle = mt_rand(0, 359) * (M_PI / 180);
			$r = mt_rand(3, 8);
			$this->wanderTarget = $this->location->add(cos($angle) * $r, 0, sin($angle) * $r);
		}
		if($this->wanderTarget !== null){
			$x = $this->wanderTarget->x - $this->location->x;
			$z = $this->wanderTarget->z - $this->location->z;
			$len = sqrt($x * $x + $z * $z);
			if($len > 0.05){
				$speed = 0.1;
				$this->smoothMotionX = $this->smoothMotionX * 0.8 + ($x / $len) * $speed * 0.2;
				$this->smoothMotionZ = $this->smoothMotionZ * 0.8 + ($z / $len) * $speed * 0.2;
				$this->location->yaw = rad2deg(atan2(-$x, $z));
			}
		}else{
			$this->smoothMotionX *= 0.9;
			$this->smoothMotionZ *= 0.9;
		}
	}

	public function getDrops() : array{
		return [
			VanillaItems::STRING()->setCount(mt_rand(0, 2)),
			VanillaItems::SPIDER_EYE()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::SPIDER_SPAWN_EGG();
	}
}
