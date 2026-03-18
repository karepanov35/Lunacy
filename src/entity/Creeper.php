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

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\Explosion;
use pocketmine\world\sound\IgniteSound;
use function mt_rand;
use function sqrt;
use function atan2;
use function cos;
use function sin;
use function abs;

class Creeper extends Living{

	public static function getNetworkTypeId() : string{ return EntityIds::CREEPER; }

	private int $fuseTime = 0;
	private int $maxFuseTime = 20;
	private bool $isIgnited = false;
	private bool $isPowered = false;
	
	private int $moveTimer = 0;
	private ?Vector3 $moveTarget = null;
	private int $idleTimer = 0;
	
	private ?Player $targetPlayer = null;
	private int $targetLostTimer = 0;
	
	private bool $isJumping = false;
	private int $jumpTimer = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.7, 0.6);
	}

	public function getName() : string{
		return "Creeper";
	}

	public function getDrops() : array{
		return [
			VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::CREEPER_SPAWN_EGG();
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->fuseTime = $nbt->getShort("Fuse", 0);
		$this->isPowered = $nbt->getByte("powered", 0) !== 0;
		$this->isIgnited = $nbt->getByte("ignited", 0) !== 0;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setShort("Fuse", $this->fuseTime);
		$nbt->setByte("powered", $this->isPowered ? 1 : 0);
		$nbt->setByte("ignited", $this->isIgnited ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::IGNITED, $this->isIgnited);
		$properties->setGenericFlag(EntityMetadataFlags::POWERED, $this->isPowered);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		
		if($this->isJumping){
			$this->jumpTimer -= $tickDiff;
			if($this->jumpTimer <= 0 || $this->onGround){
				$this->isJumping = false;
			}
		}
		
		return $hasUpdate;
	}

	public function onUpdate(int $currentTick) : bool{
		$hasUpdate = parent::onUpdate($currentTick);
		
		if($this->isIgnited){
			$this->updateExplosion();
		}else{
			$this->updateAI();
		}
		
		return $hasUpdate;
	}

	private function updateAI() : void{
		$this->findTarget();
		
		if($this->targetPlayer !== null){
			$this->updateChase();
		}else{
			$this->updateWandering();
		}
	}

	private function findTarget() : void{
		if($this->targetPlayer !== null){
			if($this->targetPlayer->isClosed() || !$this->targetPlayer->isAlive()){
				$this->targetPlayer = null;
				$this->targetLostTimer = 0;
				return;
			}
			
			$distance = $this->location->distance($this->targetPlayer->getLocation());
			if($distance > 16){
				$this->targetLostTimer++;
				if($this->targetLostTimer > 60){
					$this->targetPlayer = null;
					$this->targetLostTimer = 0;
				}
			}else{
				$this->targetLostTimer = 0;
			}
			return;
		}
		
		$nearestPlayer = null;
		$nearestDistance = 16;
		
		foreach($this->getWorld()->getPlayers() as $player){
			if($player->isCreative() || $player->isSpectator()) continue;
			
			$distance = $this->location->distance($player->getLocation());
			if($distance < $nearestDistance){
				$nearestDistance = $distance;
				$nearestPlayer = $player;
			}
		}
		
		if($nearestPlayer !== null){
			$this->targetPlayer = $nearestPlayer;
			$this->targetLostTimer = 0;
		}
	}

	private function updateChase() : void{
		if($this->targetPlayer === null) return;
		
		$distance = $this->location->distance($this->targetPlayer->getLocation());
		
		if($distance <= 3){
			if(!$this->isIgnited){
				$this->ignite();
			}
		}else{
			$this->moveTowards($this->targetPlayer->getLocation(), 0.16);
		}
	}

	private function updateWandering() : void{
		$this->moveTimer--;
		
		if($this->moveTimer <= 0){
			if($this->idleTimer > 0){
				$this->idleTimer--;
				$this->moveTarget = null;
				return;
			}
			
			if(mt_rand(0, 10) < 4){
				$this->idleTimer = mt_rand(40, 100);
				$this->moveTarget = null;
			}else{
				$this->selectNewWanderTarget();
			}
			
			$this->moveTimer = mt_rand(20, 50);
		}
		
		if($this->moveTarget !== null){
			$distance = $this->location->distance($this->moveTarget);
			
			if($distance < 0.8){
				$this->moveTarget = null;
				$this->idleTimer = mt_rand(20, 60);
			}else{
				$this->moveTowards($this->moveTarget, 0.12);
			}
		}
	}

	private function selectNewWanderTarget() : void{
		for($i = 0; $i < 10; $i++){
			$angle = mt_rand(0, 360) * M_PI / 180;
			$distance = mt_rand(3, 8);
			
			$targetX = $this->location->x + cos($angle) * $distance;
			$targetZ = $this->location->z + sin($angle) * $distance;
			
			$targetY = $this->findSafeY((int)$targetX, (int)$targetZ);
			
			if($targetY !== null){
				$this->moveTarget = new Vector3($targetX, $targetY, $targetZ);
				return;
			}
		}
		
		$this->moveTarget = null;
	}

	private function findSafeY(int $x, int $z) : ?float{
		$world = $this->getWorld();
		$currentY = (int)$this->location->y;
		
		for($y = $currentY + 2; $y >= $currentY - 3; $y--){
			$block = $world->getBlockAt($x, $y, $z);
			$blockAbove = $world->getBlockAt($x, $y + 1, $z);
			$blockAbove2 = $world->getBlockAt($x, $y + 2, $z);
			
			if($block->isSolid() && !$blockAbove->isSolid() && !$blockAbove2->isSolid()){
				return (float)($y + 1);
			}
		}
		
		return null;
	}

	private function moveTowards(Vector3 $target, float $speed) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;
		$distance = sqrt($dx * $dx + $dz * $dz);
		
		if($distance < 0.05) return;
		
		$dx /= $distance;
		$dz /= $distance;
		
		$nextX = $this->location->x + $dx * 0.8;
		$nextZ = $this->location->z + $dz * 0.8;
		
		if($this->shouldJump($nextX, $nextZ)){
			$this->tryJump();
		}
		
		$targetYaw = atan2($dz, $dx) * 180 / M_PI - 90;
		$this->smoothRotate($targetYaw);
		
		$motionX = $dx * $speed;
		$motionZ = $dz * $speed;
		
		if($this->canMoveTo($this->location->x + $motionX, $this->location->z + $motionZ)){
			$this->motion = new Vector3($motionX, $this->motion->y, $motionZ);
		}else{
			$this->tryAvoidObstacle($dx, $dz, $speed);
		}
	}

	private function shouldJump(float $nextX, float $nextZ) : bool{
		$world = $this->getWorld();
		$currentY = (int)$this->location->y;
		$checkX = (int)$nextX;
		$checkZ = (int)$nextZ;
		
		$blockInFront = $world->getBlockAt($checkX, $currentY, $checkZ);
		$blockAbove = $world->getBlockAt($checkX, $currentY + 1, $checkZ);
		
		if($blockInFront->isSolid() && !$blockAbove->isSolid()){
			return true;
		}
		
		return false;
	}

	private function tryJump() : void{
		if($this->onGround && !$this->isJumping){
			$this->isJumping = true;
			$this->jumpTimer = 10;
			$this->motion = new Vector3($this->motion->x, 0.5, $this->motion->z);
		}
	}

	private function tryAvoidObstacle(float $dx, float $dz, float $speed) : void{
		$angles = [M_PI / 4, -M_PI / 4, M_PI / 2, -M_PI / 2];
		
		foreach($angles as $angle){
			$newDx = $dx * cos($angle) - $dz * sin($angle);
			$newDz = $dx * sin($angle) + $dz * cos($angle);
			
			if($this->canMoveTo($this->location->x + $newDx * $speed, $this->location->z + $newDz * $speed)){
				$this->motion = new Vector3($newDx * $speed, $this->motion->y, $newDz * $speed);
				$this->smoothRotate(atan2($newDz, $newDx) * 180 / M_PI - 90);
				return;
			}
		}
		
		$this->motion = new Vector3(0, $this->motion->y, 0);
	}

	private function canMoveTo(float $x, float $z) : bool{
		$world = $this->getWorld();
		$currentY = (int)$this->location->y;
		
		for($y = $currentY; $y <= $currentY + 1; $y++){
			if($world->getBlockAt((int)$x, $y, (int)$z)->isSolid()) return false;
		}
		
		return true;
	}

	private function smoothRotate(float $targetYaw) : void{
		$currentYaw = $this->location->yaw;
		$diff = $targetYaw - $currentYaw;
		
		while($diff > 180) $diff -= 360;
		while($diff < -180) $diff += 360;
		
		if(abs($diff) < 2){
			$this->setRotation($targetYaw, $this->location->pitch);
			return;
		}
		
		$maxTurn = 15;
		if(abs($diff) > $maxTurn) $diff = ($diff > 0) ? $maxTurn : -$maxTurn;
		
		$this->setRotation($currentYaw + $diff, $this->location->pitch);
	}

	public function lookAt(Vector3 $target) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;
		$yaw = atan2($dz, $dx) * 180 / M_PI - 90;
		$this->smoothRotate($yaw);
	}

	public function ignite() : void{
		if(!$this->isIgnited){
			$this->isIgnited = true;
			$this->fuseTime = 0;
			$this->networkPropertiesDirty = true;
			$this->getWorld()->addSound($this->location, new IgniteSound());
		}
	}

	private function updateExplosion() : void{
		if($this->targetPlayer !== null){
			$distance = $this->location->distance($this->targetPlayer->getLocation());
			
			if($distance > 7){
				$this->cancelExplosion();
				return;
			}
			
			$this->lookAt($this->targetPlayer->getLocation());
		}
		
		$this->fuseTime++;
		$this->networkPropertiesDirty = true;
		
		if($this->fuseTime >= $this->maxFuseTime){
			$this->explode();
		}
	}

	private function cancelExplosion() : void{
		$this->isIgnited = false;
		$this->fuseTime = 0;
		$this->networkPropertiesDirty = true;
	}

	private function explode() : void{
		$explosion = new Explosion($this->location, 3.0, $this);
		$explosion->explodeA();
		$explosion->explodeB();
		
		$this->flagForDespawn();
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		
		if(!$source->isCancelled() && !$this->isIgnited){
			if(mt_rand(0, 2) === 0){
				$this->ignite();
			}
		}
	}
}


