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

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\projectile\SmallFireball;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\sound\BlazeShootSound;
use function atan2;
use function mt_rand;
use function sqrt;

class Blaze extends Living{

	private ?Player $target = null;
	private int $attackCooldown = 0;
	private int $fireTickCooldown = 0;
	private int $waterDamageCooldown = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::BLAZE;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.8, 0.6);
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	public function getName() : string{
		return "Blaze";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(20);
		$this->setHealth(20);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			return $hasUpdate;
		}

		$this->checkWaterDamage();
		$this->checkDaylightBurning();

		if($this->target !== null){
			if(!$this->target->isAlive() || $this->target->isClosed() || $this->target->isCreative() || $this->location->distanceSquared($this->target->getPosition()) > 400){
				$this->target = null;
				$this->setTargetEntity(null);
				$this->networkPropertiesDirty = true;
			}
		}

		if($this->target === null){
			$this->findNearestPlayer();
			$this->floatIdle();
		}else{
			$this->combatAI();
		}

		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}
		if($this->fireTickCooldown > 0){
			$this->fireTickCooldown -= $tickDiff;
		}
		if($this->waterDamageCooldown > 0){
			$this->waterDamageCooldown -= $tickDiff;
		}

		return true;
	}

	private function checkWaterDamage() : void{
		if($this->waterDamageCooldown > 0){
			return;
		}
		$world = $this->getWorld();
		$floorX = (int) $this->location->x;
		$floorY = (int) $this->location->y;
		$floorZ = (int) $this->location->z;
		for($y = 0; $y <= 2; $y++){
			$block = $world->getBlockAt($floorX, $floorY + $y, $floorZ);
			if($block->getTypeId() === BlockTypeIds::WATER){
				$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_DROWNING, 1));
				$this->waterDamageCooldown = 20;
				return;
			}
		}
	}

	private function checkDaylightBurning() : void{
		if($this->fireTickCooldown > 0){
			return;
		}
		$time = $this->getWorld()->getTimeOfDay();
		if($time >= 12000 || $this->isUnderwater()){
			return;
		}
		$highestY = $this->getWorld()->getHighestBlockAt((int) $this->location->x, (int) $this->location->z);
		if($highestY !== null && $this->location->y >= $highestY){
			$this->setOnFire(3);
			$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_FIRE_TICK, 1));
			$this->fireTickCooldown = 20;
		}
	}

	private function findNearestPlayer() : void{
		foreach($this->getWorld()->getPlayers() as $player){
			if($player->isAlive() && !$player->isCreative()){
				if($this->location->distanceSquared($player->getPosition()) < 400){
					if($this->canSee($player)){
						$this->target = $player;
						$this->setTargetEntity($player);
						break;
					}
				}
			}
		}
	}

	private function combatAI() : void{
		if($this->attackTime > 0){
			$tp = $this->target->getPosition();
			$lookY = $tp->y + 1.0;
			$this->lookAt(new Vector3($tp->x, $lookY, $tp->z));
			$pitch = $this->location->pitch;
			$pitch = max(-35.0, min(35.0, $pitch));
			$this->setRotation($this->location->yaw, $pitch);

			return;
		}

		$targetPos = $this->target->getPosition();
		$distSq = $this->location->distanceSquared($targetPos);
		$dist = sqrt($distSq);

		$lookY = $targetPos->y + 1.0;
		$this->lookAt(new Vector3($targetPos->x, $lookY, $targetPos->z));
		$pitch = $this->location->pitch;
		$pitch = max(-35.0, min(35.0, $pitch));
		$this->setRotation($this->location->yaw, $pitch);

		$speed = 0.15;
		if($distSq > 64){
			$dx = ($targetPos->x - $this->location->x) / $dist;
			$dz = ($targetPos->z - $this->location->z) / $dist;
			$this->motion->x = $dx * $speed;
			$this->motion->z = $dz * $speed;
			$targetY = $targetPos->y + 1 - $this->location->y;
			if($targetY > 0.1){
				$this->motion->y = 0.08;
			}elseif($targetY < -0.1){
				$this->motion->y = -0.08;
			}else{
				$this->motion->y = 0;
			}
		}elseif($distSq < 16){
			$dx = ($this->location->x - $targetPos->x) / $dist;
			$dz = ($this->location->z - $targetPos->z) / $dist;
			$this->motion->x = $dx * $speed;
			$this->motion->z = $dz * $speed;
			$this->motion->y = 0.02;
		}else{
			$this->motion->x *= 0.9;
			$this->motion->z *= 0.9;
			$this->motion->y = 0.02;
		}

		if($this->attackCooldown <= 0 && $this->canSee($this->target) && $dist < 20 && $dist > 4){
			$this->shootFireball();
			$this->attackCooldown = 32;
		}
	}

	private function floatIdle() : void{
		$this->motion->x *= 0.9;
		$this->motion->z *= 0.9;
		$this->motion->y = 0.02;
	}

	private function shootFireball() : void{
		if($this->target === null){
			return;
		}
		$from = $this->getEyePos();
		$targetPos = $this->target->getPosition();
		$targetY = $targetPos->y + 1.0;
		$diffX = $targetPos->x - $from->x;
		$diffY = ($targetY - $from->y) + 0.15;
		$diffZ = $targetPos->z - $from->z;
		$dist = sqrt($diffX * $diffX + $diffY * $diffY + $diffZ * $diffZ);
		if($dist < 0.1){
			return;
		}

		$fireball = new SmallFireball(Location::fromObject($from, $this->getWorld(), $this->location->yaw, $this->location->pitch), $this, null);
		$fireball->setMotion((new Vector3($diffX, $diffY, $diffZ))->normalize()->multiply(1.2));
		$fireball->spawnToAll();
		$this->getWorld()->addSound($this->location, new BlazeShootSound());
		$this->broadcastAnimation(new ArmSwingAnimation($this));
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

	public function getDrops() : array{
		return [
			VanillaItems::BLAZE_ROD()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 10;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::BLAZE_SPAWN_EGG();
	}
}
