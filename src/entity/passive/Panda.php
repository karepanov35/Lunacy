<?php

/*
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
 */

declare(strict_types=1);

namespace pocketmine\entity\passive;

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Ageable;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\sound\ItemUseOnBlockSound;
use function abs;
use function atan2;
use function cos;
use function min;
use function mt_rand;
use function sin;
use function sqrt;
use const M_PI;

class Panda extends Living implements Ageable{

	public static function getNetworkTypeId() : string{
		return EntityIds::PANDA;
	}

	private int $age = 0;
	private bool $ageLocked = false;
	private const BABY_AGE = -24000;

	private int $inLoveTicks = 0;
	private bool $tamed = false;
	private int $sitEatingTicks = 0;
	private bool $sitting = false;

	private int $moveTimer = 0;
	private ?Vector3 $moveTarget = null;
	private int $idleTimer = 0;

	private bool $isPanicking = false;
	private int $panicTimer = 0;
	private ?Vector3 $panicTarget = null;

	private bool $isJumping = false;
	private int $jumpTimer = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.25, 1.125);
	}

	public function getName() : string{
		return "Panda";
	}

	public function getDrops() : array{
		if($this->isBaby()){
			return [];
		}
		return [
			VanillaItems::BAMBOO()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::PANDA_SPAWN_EGG();
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
			if($this->age > 0){
				$this->age = 0;
			}
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

	public function isTamed() : bool{
		return $this->tamed;
	}

	public function setTamed(bool $tamed) : void{
		$this->tamed = $tamed;
		$this->networkPropertiesDirty = true;
	}

	public function isSitting() : bool{
		return $this->sitting;
	}

	public function setSitting(bool $sitting) : void{
		$this->sitting = $sitting;
		$this->networkPropertiesDirty = true;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(20);
		$this->setHealth((float) min($this->getHealth(), 20));
		$this->age = $nbt->getInt("Age", 0);
		$this->ageLocked = $nbt->getByte("AgeLocked", 0) !== 0;
		$this->inLoveTicks = $nbt->getInt("InLove", 0);
		$this->tamed = $nbt->getByte("IsTamed", 0) === 1;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt("Age", $this->age);
		$nbt->setByte("AgeLocked", $this->ageLocked ? 1 : 0);
		$nbt->setInt("InLove", $this->inLoveTicks);
		$nbt->setByte("IsTamed", $this->tamed ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
		$properties->setGenericFlag(EntityMetadataFlags::TAMED, $this->tamed);
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
		$properties->setGenericFlag(EntityMetadataFlags::EATING, $this->sitting);
		$properties->setFloat(EntityMetadataProperties::SITTING_AMOUNT, $this->sitting ? 1.0 : 0.0);
		$properties->setFloat(EntityMetadataProperties::SITTING_AMOUNT_PREVIOUS, $this->sitting ? 1.0 : 0.0);
	}

	public function onUpdate(int $currentTick) : bool{
		if($this->sitEatingTicks > 0){
			$this->sitEatingTicks--;
			$this->moveTarget = null;
			$this->motion = new Vector3(0, $this->motion->y, 0);

			if($this->sitEatingTicks % 15 === 0){
				$this->getWorld()->addSound($this->getLocation(), new ItemUseOnBlockSound(VanillaBlocks::OAK_WOOD()));
			}

			if($this->sitEatingTicks % 20 === 0){
				$this->broadcastHeldItem(VanillaItems::BAMBOO());
			}

			if($this->sitEatingTicks === 0){
				$this->setSitting(false);
				$this->broadcastHeldItem(VanillaItems::AIR());
			}
		}

		if($this->inLoveTicks > 0){
			$this->inLoveTicks--;
		}

		$hasUpdate = parent::onUpdate($currentTick);
		if($this->sitEatingTicks <= 0){
			$this->updateAI();
		}
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

		if(!$this->ageLocked && $this->age < 0){
			$this->ageUp($tickDiff);
			if($this->age === 0){
				$this->broadcastAnimation(new \pocketmine\entity\animation\BabyAnimalFeedAnimation($this));
			}
		}

		return $hasUpdate;
	}

	private function updateAI() : void{
		if($this->isPanicking){
			$this->updatePanic();
			return;
		}

		if($this->seekBambooItem()){
			return;
		}

		$this->updateWandering();
	}

	private function seekBambooItem() : bool{
		$bambooEntity = null;
		$closestDist = 999.0;
		$bambooTypeId = VanillaItems::BAMBOO()->getTypeId();

		foreach($this->getWorld()->getEntities() as $entity){
			if(!$entity instanceof ItemEntity){
				continue;
			}
			if($entity->getItem()->getTypeId() !== $bambooTypeId){
				continue;
			}
			$dist = $this->location->distanceSquared($entity->location);
			if($dist < 144 && $dist < $closestDist){
				$closestDist = $dist;
				$bambooEntity = $entity;
			}
		}

		if($bambooEntity === null){
			return false;
		}

		$target = $bambooEntity->location;
		if($closestDist <= 2.5){
			$bambooEntity->close();
			$this->setTamed(true);
			$this->getWorld()->addParticle($this->location->add(0, 1.0, 0), new HeartParticle(3));
			$this->startEating();
			return true;
		}

		$this->moveTowards($target, $this->isBaby() ? 0.14 : 0.12, false);
		return true;
	}

	private function startEating() : void{
		$this->sitEatingTicks = 100;
		$this->setSitting(true);
		$this->moveTarget = null;
		$this->motion = new Vector3(0, $this->motion->y, 0);
		$this->broadcastHeldItem(VanillaItems::BAMBOO());
	}

	private function broadcastHeldItem(Item $item) : void{
		foreach($this->getViewers() as $viewer){
			$viewer->getNetworkSession()->sendDataPacket(MobEquipmentPacket::create(
				$this->getId(),
				ItemStackWrapper::legacy($viewer->getNetworkSession()->getTypeConverter()->coreItemStackToNet($item)),
				0,
				0,
				ContainerIds::INVENTORY
			));
		}
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
			$this->moveTowards($this->panicTarget, $this->isBaby() ? 0.28 : 0.24, true);
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

			for($y = (int) $targetY + 3; $y >= (int) $targetY - 3; $y--){
				$block = $world->getBlockAt((int) $targetX, $y, (int) $targetZ);
				$blockAbove = $world->getBlockAt((int) $targetX, $y + 1, (int) $targetZ);
				$blockAbove2 = $world->getBlockAt((int) $targetX, $y + 2, (int) $targetZ);

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
				$this->moveTowards($this->moveTarget, $this->isBaby() ? 0.08 : 0.1, false);
			}
		}
	}

	private function selectNewWanderTarget() : void{
		for($i = 0; $i < 10; $i++){
			$angle = mt_rand(0, 360) * M_PI / 180;
			$distance = mt_rand(3, 8);

			$targetX = $this->location->x + cos($angle) * $distance;
			$targetZ = $this->location->z + sin($angle) * $distance;

			$targetY = $this->findSafeY((int) $targetX, (int) $targetZ);

			if($targetY !== null){
				$this->moveTarget = new Vector3($targetX, $targetY, $targetZ);
				return;
			}
		}

		$this->moveTarget = null;
	}

	private function findSafeY(int $x, int $z) : ?float{
		$world = $this->getWorld();
		$currentY = (int) $this->location->y;

		for($y = $currentY + 2; $y >= $currentY - 3; $y--){
			$block = $world->getBlockAt($x, $y, $z);
			$blockAbove = $world->getBlockAt($x, $y + 1, $z);
			$blockAbove2 = $world->getBlockAt($x, $y + 2, $z);

			if($block->isSolid() && !$blockAbove->isSolid() && !$blockAbove2->isSolid()){
				return (float) ($y + 1);
			}
		}

		return null;
	}

	private function moveTowards(Vector3 $target, float $speed, bool $isPanic) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;
		$distance = sqrt($dx * $dx + $dz * $dz);

		if($distance < 0.05){
			return;
		}

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
		$currentY = (int) $this->location->y;
		$checkX = (int) round($nextX);
		$checkZ = (int) round($nextZ);

		$blockAtFeet = $world->getBlockAt($checkX, $currentY, $checkZ);
		$blockAboveFeet = $world->getBlockAt($checkX, $currentY + 1, $checkZ);

		if($blockAtFeet->isSolid() && !$blockAboveFeet->isSolid()){
			return true;
		}

		$blockAboveGround = $world->getBlockAt($checkX, $currentY + 1, $checkZ);
		if($blockAboveGround->isSolid()){
			$blockAtHead = $world->getBlockAt($checkX, $currentY + 2, $checkZ);
			if(!$blockAtHead->isSolid()){
				return true;
			}
		}

		return false;
	}

	private function tryJump(float $dirX, float $dirZ) : void{
		if($this->onGround && !$this->isJumping){
			$this->isJumping = true;
			$this->jumpTimer = 12;
			$jumpY = $this->isBaby() ? 0.48 : 0.58;
			$forward = 0.32;
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
		$currentY = (int) $this->location->y;

		for($y = $currentY; $y <= $currentY + 2; $y++){
			if($world->getBlockAt((int) $x, $y, (int) $z)->isSolid()){
				return false;
			}
		}

		return true;
	}

	private function smoothRotate(float $targetYaw) : void{
		$currentYaw = $this->location->yaw;
		$diff = $targetYaw - $currentYaw;

		while($diff > 180){
			$diff -= 360;
		}
		while($diff < -180){
			$diff += 360;
		}

		if(abs($diff) < 4){
			$this->setRotation($targetYaw, $this->location->pitch);
			return;
		}

		$maxTurn = 12;
		if(abs($diff) > $maxTurn){
			$diff = ($diff > 0) ? $maxTurn : -$maxTurn;
		}

		$this->setRotation($currentYaw + $diff, $this->location->pitch);
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if(!$source->isCancelled()){
			$this->startPanic();
		}
	}

	private function startPanic() : void{
		$this->isPanicking = true;
		$this->panicTimer = mt_rand(80, 150);
		$this->panicTarget = null;
		$this->setRotation($this->location->yaw, 0);
	}

	private function isBreedingItem(Item $item) : bool{
		return $item->getTypeId() === VanillaItems::BAMBOO()->getTypeId();
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if(!$this->isBreedingItem($item)){
			return false;
		}

		if($this->age === 0 && $this->inLoveTicks <= 0){
			$this->inLoveTicks = 600;
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->getWorld()->addParticle($this->location->add(0, $this->getEyeHeight() + 0.5, 0), new HeartParticle(2));
			return true;
		}

		if($this->age < 0){
			$this->ageUp((int) abs($this->age * 0.1));
			if($this->age >= 0){
				$this->age = 0;
			}
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->getWorld()->addParticle($this->location->add(0, $this->getEyeHeight() + 0.5, 0), new HeartParticle(2));
			return true;
		}

		return false;
	}
}
