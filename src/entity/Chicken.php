<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GPL-2.0 license as published by
 * the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 *
 * @author Karepanov
 * @link https://github.com/karepanov35/Lunacy
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
use pocketmine\world\sound\PopSound;
use function abs;
use function atan2;
use function cos;
use function min;
use function mt_rand;
use function sin;
use function sqrt;
use const M_PI;

/**
 * Поведение в духе Lumi EntityChicken: кладка яйца 6000–12000 тиков, семена / любовь, без урона от падения.
 */
class Chicken extends Living implements Ageable{

	public static function getNetworkTypeId() : string{ return EntityIds::CHICKEN; }

	private int $age = 0;
	private bool $ageLocked = false;
	private const BABY_AGE = -24000;

	private int $eggLayTime;

	private int $moveTimer = 0;
	private ?Vector3 $moveTarget = null;
	private int $idleTimer = 0;

	private bool $isPanicking = false;
	private int $panicTimer = 0;
	private ?Vector3 $panicTarget = null;

	private ?Player $temptingPlayer = null;
	private int $temptCooldown = 0;

	private bool $isJumping = false;
	private int $jumpTimer = 0;

	/** После размножения (ванильная задержка) */
	private int $loveCooldown = 0;
	/** «В любви» — визуально/логика размножения */
	private int $loveModeTicks = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.7, 0.4);
	}

	public function getName() : string{
		return "Chicken";
	}

	public function getDrops() : array{
		if($this->isBaby()) return [];
		return [
			VanillaItems::FEATHER()->setCount(mt_rand(0, 2)),
			$this->isOnFire() ? VanillaItems::COOKED_CHICKEN() : VanillaItems::RAW_CHICKEN(),
		];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::CHICKEN_SPAWN_EGG();
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function getAge() : int{
		return $this->age;
	}

	public function setAge(int $age) : void{
		$this->age = $age;
	}

	public function ageUp(int $amount = 1) : void{
		if(!$this->ageLocked){
			$this->age += $amount;
			if($this->age > 0) $this->age = 0;
		}
	}

	public function setBaby(bool $baby = true) : void{
		$this->age = $baby ? self::BABY_AGE : 0;
	}

	public function setAgeLocked(bool $locked) : void{
		$this->ageLocked = $locked;
	}

	public function isAgeLocked() : bool{
		return $this->ageLocked;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt("Age", $this->age);
		$nbt->setByte("AgeLocked", $this->ageLocked ? 1 : 0);
		$nbt->setInt("EggLayTime", $this->eggLayTime);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->age = $nbt->getInt("Age", 0);
		$this->ageLocked = $nbt->getByte("AgeLocked", 0) !== 0;
		$this->eggLayTime = $nbt->getInt("EggLayTime", $this->randomEggLayTime());
		// Как у мелких мобов в Lumi/ваниле: без stepHeight движок не поднимает на 1 блок — только «втыкается» в стену.
		$this->setStepHeight(0.6);
		$this->setMaxHealth(4);
		$this->setHealth(min($this->getHealth(), 4.0));
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
	}

	public function onUpdate(int $currentTick) : bool{
		$hasUpdate = parent::onUpdate($currentTick);
		$this->updateAI();
		return $hasUpdate;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->isJumping){
			$this->jumpTimer -= $tickDiff;
			if($this->jumpTimer <= 0 || $this->onGround){
				$this->isJumping = false;
			}
		}

		if($this->loveCooldown > 0){
			$this->loveCooldown -= $tickDiff;
			if($this->loveCooldown < 0){
				$this->loveCooldown = 0;
			}
		}
		if($this->loveModeTicks > 0){
			$this->loveModeTicks -= $tickDiff;
			if($this->loveModeTicks < 0){
				$this->loveModeTicks = 0;
			}
		}

		if(!$this->ageLocked && $this->age < 0){
			$this->ageUp($tickDiff);
			if($this->age === 0){
				$this->broadcastAnimation(new \pocketmine\entity\animation\BabyAnimalFeedAnimation($this));
			}
		}

		if(!$this->isBaby()){
			if($this->eggLayTime > 0){
				$this->eggLayTime -= $tickDiff;
			}else{
				$this->getWorld()->dropItem($this->location->asVector3(), $this->getEggItem());
				$this->getWorld()->addSound($this->location->asVector3(), new PopSound());
				$this->eggLayTime = $this->randomEggLayTime();
			}
		}

		return $hasUpdate;
	}

	private function randomEggLayTime() : int{
		return mt_rand(6000, 12000);
	}

	private function getEggItem() : Item{
		return VanillaItems::EGG();
	}

	private function updateAI() : void{
		if($this->isPanicking){
			$this->updatePanic();
			return;
		}

		if($this->checkTempt()) return;

		$this->updateWandering();
	}

	private function checkTempt() : bool{
		if($this->temptCooldown > 0){
			$this->temptCooldown--;
			if($this->temptCooldown <= 0) $this->temptingPlayer = null;
		}

		$nearestPlayer = null;
		$nearestDistance = 8.0;

		foreach($this->getWorld()->getPlayers() as $player){
			$distance = $this->location->distance($player->getLocation());
			if($distance < $nearestDistance && $this->isHoldingSeeds($player)){
				$nearestDistance = $distance;
				$nearestPlayer = $player;
			}
		}

		if($nearestPlayer !== null){
			$this->temptingPlayer = $nearestPlayer;
			$this->temptCooldown = 5;
			$playerPos = $nearestPlayer->getLocation();

			if($nearestDistance > 2.5){
				$this->moveTowards($playerPos, $this->isBaby() ? 0.22 : 0.18, false);
			}else{
				$this->lookAt($playerPos);
			}
			return true;
		}

		$this->temptingPlayer = null;
		return false;
	}

	private function isHoldingSeeds(Player $player) : bool{
		$item = $player->getInventory()->getItemInHand();
		$id = $item->getTypeId();
		return $id === VanillaItems::WHEAT_SEEDS()->getTypeId()
			|| $id === VanillaItems::BEETROOT_SEEDS()->getTypeId()
			|| $id === VanillaItems::MELON_SEEDS()->getTypeId()
			|| $id === VanillaItems::PUMPKIN_SEEDS()->getTypeId();
	}

	public function lookAt(Vector3 $target) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;
		$yaw = atan2($dz, $dx) * 180 / M_PI - 90;
		$this->smoothRotate($yaw);
	}

	private function updatePanic() : void{
		$this->panicTimer--;

		if($this->panicTimer <= 0){
			$this->stopPanic();
			return;
		}

		if($this->panicTarget === null || $this->location->distance($this->panicTarget) < 1.5){
			$this->panicTarget = $this->findPanicTarget();
		}

		if($this->panicTarget !== null){
			$this->moveTowards($this->panicTarget, $this->isBaby() ? 0.32 : 0.28, true);
		}
	}

	private function findPanicTarget() : ?Vector3{
		$world = $this->getWorld();

		for($i = 0; $i < 10; $i++){
			$angle = mt_rand(0, 360) * M_PI / 180;
			$distance = mt_rand(8, 16);

			$targetX = $this->location->x + cos($angle) * $distance;
			$targetZ = $this->location->z + sin($angle) * $distance;
			$targetY = $this->location->y;

			for($y = (int)$targetY + 3; $y >= (int)$targetY - 3; $y--){
				$block = $world->getBlockAt((int)$targetX, $y, (int)$targetZ);
				$blockAbove = $world->getBlockAt((int)$targetX, $y + 1, (int)$targetZ);
				$blockAbove2 = $world->getBlockAt((int)$targetX, $y + 2, (int)$targetZ);

				if($block->isSolid() && !$blockAbove->isSolid() && !$blockAbove2->isSolid()){
					return new Vector3($targetX, $y + 1, $targetZ);
				}
			}
		}

		return null;
	}

	private function stopPanic() : void{
		$this->isPanicking = false;
		$this->panicTimer = 0;
		$this->panicTarget = null;
		$this->moveTarget = null;
		$this->moveTimer = 0;
	}

	private function updateWandering() : void{
		$this->moveTimer--;

		if($this->moveTimer <= 0){
			if($this->idleTimer > 0){
				$this->idleTimer--;
				$this->moveTarget = null;
				return;
			}

			if(mt_rand(0, 10) < 3){
				$this->idleTimer = mt_rand(40, 100);
				$this->moveTarget = null;
			}else{
				$this->selectNewWanderTarget();
			}

			$this->moveTimer = mt_rand(20, 50);
		}

		if($this->moveTarget !== null){
			$distance = $this->location->distance($this->moveTarget);

			if($distance < 1.0){
				$this->moveTarget = null;
				$this->idleTimer = mt_rand(20, 60);
				$this->motion = new Vector3(0, $this->motion->y, 0);
			}else{
				$this->moveTowards($this->moveTarget, $this->isBaby() ? 0.1 : 0.12, false);
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

	private function moveTowards(Vector3 $target, float $speed, bool $isPanic) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;
		$distance = sqrt($dx * $dx + $dz * $dz);

		if($distance < 0.05) return;

		$dx /= $distance;
		$dz /= $distance;

		$nextX = $this->location->x + $dx * 0.5;
		$nextZ = $this->location->z + $dz * 0.5;

		if($this->shouldJump($nextX, $nextZ)){
			$this->tryJump($dx, $dz);
			return;
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
		$checkX = (int)round($nextX);
		$checkZ = (int)round($nextZ);

		$blockAtFeet = $world->getBlockAt($checkX, $currentY, $checkZ);
		$blockAboveFeet = $world->getBlockAt($checkX, $currentY + 1, $checkZ);

		if($blockAtFeet->isSolid() && !$blockAboveFeet->isSolid()) return true;

		$blockAboveGround = $world->getBlockAt($checkX, $currentY + 1, $checkZ);
		if($blockAboveGround->isSolid()){
			$blockAtHead = $world->getBlockAt($checkX, $currentY + 2, $checkZ);
			if(!$blockAtHead->isSolid()) return true;
		}

		return false;
	}

	private function tryJump(float $dirX, float $dirZ) : void{
		if($this->onGround && !$this->isJumping){
			$this->isJumping = true;
			$this->jumpTimer = 14;
			// Согласовано с Living::getJumpVelocity() (~0.42) + чуть сильнее вперёд для перепрыга 1 блока
			$jumpY = max($this->getJumpVelocity(), $this->isBaby() ? 0.45 : 0.5);
			$forward = 0.38;
			$this->motion = new Vector3($dirX * $forward, $jumpY, $dirZ * $forward);
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
		$this->moveTarget = null;
		$this->idleTimer = 15;
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

		if(abs($diff) < 4){
			$this->setRotation($targetYaw, $this->location->pitch);
			return;
		}

		$maxTurn = 12;
		if(abs($diff) > $maxTurn) $diff = ($diff > 0) ? $maxTurn : -$maxTurn;

		$this->setRotation($currentYaw + $diff, $this->location->pitch);
	}

	public function attack(EntityDamageEvent $source) : void{
		if($source->getCause() === EntityDamageEvent::CAUSE_FALL){
			return;
		}
		parent::attack($source);
		if(!$source->isCancelled()){
			$this->startPanic();
		}
	}

	private function startPanic() : void{
		$this->isPanicking = true;
		$this->panicTimer = mt_rand(80, 150);
		$this->panicTarget = null;
		$this->temptingPlayer = null;
		$this->setRotation($this->location->yaw, 0);
	}

	private function isSeedItem(Item $item) : bool{
		$id = $item->getTypeId();
		return $id === VanillaItems::WHEAT_SEEDS()->getTypeId()
			|| $id === VanillaItems::BEETROOT_SEEDS()->getTypeId()
			|| $id === VanillaItems::MELON_SEEDS()->getTypeId()
			|| $id === VanillaItems::PUMPKIN_SEEDS()->getTypeId();
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if(!$this->isSeedItem($item)){
			return false;
		}

		if($this->isBaby()){
			$this->ageUp(1200);
			$this->broadcastAnimation(new \pocketmine\entity\animation\BabyAnimalFeedAnimation($this));
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			return true;
		}

		if($this->getHealth() < $this->getMaxHealth()){
			$this->setHealth(min($this->getMaxHealth(), $this->getHealth() + 2));
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			return true;
		}

		if($this->loveCooldown <= 0){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->loveModeTicks = 600;
			return true;
		}

		return false;
	}

	/** Пометка курицы как «размножившейся» (для будущего размножения). */
	public function enterBreedingCooldown() : void{
		$this->loveCooldown = 6000;
		$this->loveModeTicks = 0;
	}
}
