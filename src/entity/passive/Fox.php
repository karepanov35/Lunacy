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

use pocketmine\block\SweetBerryBush;
use pocketmine\entity\Ageable;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\EntityDamageByBlockEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\sound\PopSound;
use function abs;
use function atan2;
use function cos;
use function floor;
use function max;
use function min;
use function mt_rand;
use function round;
use function sin;
use function sqrt;
use const M_PI;

class Fox extends Living implements Ageable{

	public static function getNetworkTypeId() : string{
		return EntityIds::FOX;
	}

	private int $age = 0;
	private bool $ageLocked = false;
	private const BABY_AGE = -24000;

	private int $inLoveTicks = 0;

	private ?Item $heldItem = null;
	private ?ItemEntity $targetItemEntity = null;
	private int $spitDelay = 0;

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

	private int $stuckTicks = 0;
	private ?Vector3 $lastStuckCheckPos = null;
	private int $avoidSide = 1;
	private float $walkSpeedMul = 1.0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.6, 0.7);
	}

	public function getName() : string{
		return "Fox";
	}

	public function getDrops() : array{
		$drops = [];
		if($this->heldItem !== null && !$this->heldItem->isNull()){
			$drops[] = clone $this->heldItem;
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 2);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::FOX_SPAWN_EGG();
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

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(10);
		$this->setHealth((float) min($this->getHealth(), 10));
		$this->age = $nbt->getInt("Age", 0);
		$this->ageLocked = $nbt->getByte("AgeLocked", 0) !== 0;
		$this->inLoveTicks = $nbt->getInt("InLove", 0);

		$heldItemTag = $nbt->getTag("HeldItem");
		if($heldItemTag instanceof CompoundTag){
			$this->heldItem = Item::nbtDeserialize($heldItemTag);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt("Age", $this->age);
		$nbt->setByte("AgeLocked", $this->ageLocked ? 1 : 0);
		$nbt->setInt("InLove", $this->inLoveTicks);
		if($this->heldItem !== null && !$this->heldItem->isNull()){
			$nbt->setTag("HeldItem", $this->heldItem->nbtSerialize());
		}
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		if($this->heldItem !== null && !$this->heldItem->isNull()){
			$player->getNetworkSession()->sendDataPacket(MobEquipmentPacket::create(
				$this->getId(),
				ItemStackWrapper::legacy($player->getNetworkSession()->getTypeConverter()->coreItemStackToNet($this->heldItem)),
				0,
				0,
				ContainerIds::INVENTORY
			));
		}
	}

	public function onUpdate(int $currentTick) : bool{
		if($this->spitDelay > 0){
			$this->spitDelay--;
		}
		if($this->inLoveTicks > 0){
			$this->inLoveTicks--;
			if($this->inLoveTicks % 20 === 0){
				$this->getWorld()->addParticle($this->location->add(0, $this->getEyeHeight() + 0.5, 0), new HeartParticle(1));
			}
		}

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

		if(!$this->ageLocked && $this->age < 0){
			$this->ageUp($tickDiff);
		}

		return $hasUpdate;
	}

	private function updateAI() : void{
		$this->updateStuckDetection();

		if($this->isPanicking){
			$this->updatePanic();
			return;
		}

		if($this->seekBerryItem()){
			return;
		}

		if($this->checkTempt()){
			return;
		}

		$this->updateWandering();
	}

	private function updateStuckDetection() : void{
		$pos = $this->location->asVector3();
		if($this->lastStuckCheckPos === null){
			$this->lastStuckCheckPos = $pos;
			$this->stuckTicks = 0;
			return;
		}

		$moved = $pos->distanceSquared($this->lastStuckCheckPos);
		$tryingToMove = ($this->moveTarget !== null || $this->targetItemEntity !== null || $this->temptingPlayer !== null || $this->isPanicking)
			&& !$this->isJumping;

		if($tryingToMove && $moved < 0.02 && $this->onGround){
			$this->stuckTicks++;
			if($this->stuckTicks >= 60){
				$this->unstuck();
			}
		}else{
			if($moved >= 0.02){
				$this->stuckTicks = 0;
				$this->lastStuckCheckPos = $pos;
			}
		}
	}

	private function unstuck() : void{
		$this->stuckTicks = 0;
		$this->lastStuckCheckPos = $this->location->asVector3();
		$this->avoidSide *= -1;
		$this->moveTarget = null;
		$this->targetItemEntity = null;
		$this->temptingPlayer = null;
		$this->panicTarget = null;
		$this->idleTimer = 0;
		$this->moveTimer = 0;

		$yaw = $this->location->yaw + ($this->avoidSide * 90) + mt_rand(-20, 20);
		$rad = $yaw * M_PI / 180;
		$dirX = -sin($rad);
		$dirZ = cos($rad);

		for($dist = 4; $dist <= 8; $dist++){
			$tx = $this->location->x + $dirX * $dist;
			$tz = $this->location->z + $dirZ * $dist;
			$ty = $this->findSafeY((int) $tx, (int) $tz);
			if($ty !== null){
				$this->moveTarget = new Vector3($tx, $ty, $tz);
				$this->moveTimer = 40;
				return;
			}
		}

		$this->selectNewWanderTarget();
		$this->moveTimer = 40;
	}

	private function isBerryItem(Item $item) : bool{
		$typeId = $item->getTypeId();
		return $typeId === VanillaItems::SWEET_BERRIES()->getTypeId()
			|| $typeId === VanillaItems::GLOW_BERRIES()->getTypeId();
	}

	private function hasHeldItem() : bool{
		return $this->heldItem !== null && !$this->heldItem->isNull();
	}

	private function equipItem(Item $item) : void{
		$this->heldItem = $item->isNull() ? null : clone $item;
		$this->broadcastHeldItem($this->heldItem ?? VanillaItems::AIR());
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

	private function seekBerryItem() : bool{
		if($this->hasHeldItem()){
			$this->targetItemEntity = null;
			return false;
		}
		if($this->spitDelay > 0){
			return false;
		}

		if($this->targetItemEntity !== null && ($this->targetItemEntity->isClosed() || !$this->targetItemEntity->isAlive() || $this->targetItemEntity->getWorld() !== $this->getWorld())){
			$this->targetItemEntity = null;
		}

		if($this->targetItemEntity === null){
			$nearest = null;
			$minDist = 36.0;
			foreach($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(6, 3, 6), $this) as $entity){
				if(!$entity instanceof ItemEntity || !$entity->isAlive() || $entity->isClosed()){
					continue;
				}
				if(!$this->isBerryItem($entity->getItem())){
					continue;
				}
				$dist = $this->location->distanceSquared($entity->getLocation());
				if($dist < $minDist){
					$minDist = $dist;
					$nearest = $entity;
				}
			}
			$this->targetItemEntity = $nearest;
		}

		if($this->targetItemEntity === null){
			return false;
		}

		$dist = $this->location->distanceSquared($this->targetItemEntity->getLocation());
		if($dist < 1.2){
			$this->pickupBerry($this->targetItemEntity);
			return true;
		}

		$this->moveTowards($this->targetItemEntity->getLocation(), $this->isBaby() ? 0.15 : 0.13, false);
		return true;
	}

	private function pickupBerry(ItemEntity $itemEntity) : void{
		if($itemEntity->isClosed() || !$itemEntity->isAlive() || $this->hasHeldItem()){
			$this->targetItemEntity = null;
			return;
		}

		$stack = $itemEntity->getItem();
		if(!$this->isBerryItem($stack) || $stack->isNull() || $stack->getCount() < 1){
			$this->targetItemEntity = null;
			return;
		}

		$single = clone $stack;
		$single->setCount(1);
		$count = $stack->getCount();

		$this->targetItemEntity = null;
		$this->spitDelay = 40;

		if($count <= 1){
			$itemEntity->close();
		}else{
			$itemEntity->setStackSize($count - 1);
		}

		$this->equipItem($single);
		$this->getWorld()->addSound($this->location, new PopSound());
	}

	private function checkTempt() : bool{
		if($this->temptCooldown > 0){
			$this->temptCooldown--;
			if($this->temptCooldown <= 0){
				$this->temptingPlayer = null;
			}
		}

		$nearestPlayer = null;
		$nearestDistance = 8.0;

		foreach($this->getWorld()->getPlayers() as $player){
			$distance = $this->location->distance($player->getLocation());
			if($distance < $nearestDistance && $this->isBerryItem($player->getInventory()->getItemInHand())){
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

	public function lookAt(Vector3 $target) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;
		$this->smoothRotate(atan2($dz, $dx) * 180 / M_PI - 90);
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
			$this->moveTowards($this->panicTarget, $this->isBaby() ? 0.30 : 0.26, true);
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
				if($this->idleTimer % 15 === 0){
					$this->setRotation($this->location->yaw + mt_rand(-25, 25), 0);
				}
				$this->motion = new Vector3(0, $this->motion->y, 0);
				return;
			}

			if(mt_rand(0, 10) < 4){
				$this->idleTimer = mt_rand(30, 80);
				$this->moveTarget = null;
				$this->motion = new Vector3(0, $this->motion->y, 0);
			}else{
				$this->selectNewWanderTarget();
				$this->walkSpeedMul = 0.85 + (mt_rand(0, 30) / 100);
			}

			$this->moveTimer = mt_rand(35, 70);
		}

		if($this->moveTarget !== null){
			$distance = $this->location->distance($this->moveTarget);

			if($distance < 1.2){
				$this->moveTarget = null;
				$this->idleTimer = mt_rand(25, 55);
				$this->motion = new Vector3(0, $this->motion->y, 0);
			}else{
				$base = $this->isBaby() ? 0.09 : 0.11;
				$this->moveTowards($this->moveTarget, $base * $this->walkSpeedMul, false);
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

		if($distance < 0.08){
			return;
		}

		$dx /= $distance;
		$dz /= $distance;

		if($this->isJumping){
			$this->motion = new Vector3($dx * $speed * 1.15, $this->motion->y, $dz * $speed * 1.15);
			$this->smoothRotate(atan2($dz, $dx) * 180 / M_PI - 90);
			return;
		}

		$lookAhead = 0.55;
		$nextX = $this->location->x + $dx * $lookAhead;
		$nextZ = $this->location->z + $dz * $lookAhead;
		$obstacleHeight = $this->getObstacleHeight($nextX, $nextZ);

		if($obstacleHeight >= 2){
			$this->tryAvoidObstacle($dx, $dz, $speed);
			return;
		}

		if($obstacleHeight === 1){
			if($this->onGround){
				$this->tryJump($dx, $dz);
			}
			return;
		}

		$targetYaw = atan2($dz, $dx) * 180 / M_PI - 90;
		$this->smoothRotate($targetYaw);

		$desiredX = $dx * $speed;
		$desiredZ = $dz * $speed;

		$blend = $isPanic ? 0.55 : 0.35;
		$motionX = $this->motion->x + ($desiredX - $this->motion->x) * $blend;
		$motionZ = $this->motion->z + ($desiredZ - $this->motion->z) * $blend;

		if($this->canMoveTo($this->location->x + $motionX, $this->location->z + $motionZ)){
			$this->motion = new Vector3($motionX, $this->motion->y, $motionZ);
		}else{
			$this->tryAvoidObstacle($dx, $dz, $speed);
		}
	}

	private function getObstacleHeight(float $nextX, float $nextZ) : int{
		$world = $this->getWorld();
		$y = (int) floor($this->location->y + 0.001);
		$checkX = (int) floor($nextX);
		$checkZ = (int) floor($nextZ);

		$foxX = (int) floor($this->location->x);
		$foxZ = (int) floor($this->location->z);
		if($checkX === $foxX && $checkZ === $foxZ){
			return 0;
		}

		$atFeet = $world->getBlockAt($checkX, $y, $checkZ)->isSolid();
		$atBody = $world->getBlockAt($checkX, $y + 1, $checkZ)->isSolid();
		$atHead = $world->getBlockAt($checkX, $y + 2, $checkZ)->isSolid();

		if(!$atFeet && !$atBody){
			return 0;
		}

		if($atFeet && $atBody){
			return 2;
		}

		if(!$atFeet && $atBody){
			return 2;
		}

		if($atFeet && !$atBody && !$atHead){
			return 1;
		}

		return 2;
	}

	private function tryJump(float $dirX, float $dirZ) : void{
		if($this->onGround && !$this->isJumping){
			$this->isJumping = true;
			$this->jumpTimer = 14;
			$jumpY = $this->isBaby() ? 0.42 : 0.50;
			$forward = 0.28;
			$this->motion = new Vector3($dirX * $forward, $jumpY, $dirZ * $forward);
		}
	}

	private function tryAvoidObstacle(float $dx, float $dz, float $speed) : void{
		$speed = max($speed, 0.12);
		$preferred = $this->avoidSide;
		$angles = [
			$preferred * M_PI / 3,
			-$preferred * M_PI / 3,
			$preferred * M_PI / 2,
			-$preferred * M_PI / 2,
			$preferred * 2 * M_PI / 3,
			-$preferred * 2 * M_PI / 3,
		];

		foreach($angles as $angle){
			$newDx = $dx * cos($angle) - $dz * sin($angle);
			$newDz = $dx * sin($angle) + $dz * cos($angle);
			$len = sqrt($newDx * $newDx + $newDz * $newDz);
			if($len < 0.001){
				continue;
			}
			$newDx /= $len;
			$newDz /= $len;

			$probeX = $this->location->x + $newDx * 0.9;
			$probeZ = $this->location->z + $newDz * 0.9;
			$height = $this->getObstacleHeight($probeX, $probeZ);

			if($height >= 2){
				continue;
			}

			if($height === 1){
				if($this->onGround && !$this->isJumping){
					$this->tryJump($newDx, $newDz);
				}
				return;
			}

			$stepX = $this->location->x + $newDx * $speed;
			$stepZ = $this->location->z + $newDz * $speed;
			if($this->canMoveTo($stepX, $stepZ)){
				$blend = 0.4;
				$this->motion = new Vector3(
					$this->motion->x + ($newDx * $speed - $this->motion->x) * $blend,
					$this->motion->y,
					$this->motion->z + ($newDz * $speed - $this->motion->z) * $blend
				);
				$this->smoothRotate(atan2($newDz, $newDx) * 180 / M_PI - 90);
				return;
			}
		}

		$this->avoidSide *= -1;
		$this->stuckTicks = max($this->stuckTicks, 45);
	}

	private function canMoveTo(float $x, float $z) : bool{
		return $this->getObstacleHeight($x, $z) === 0;
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

		$maxTurn = 8;
		if(abs($diff) > $maxTurn){
			$diff = ($diff > 0) ? $maxTurn : -$maxTurn;
		}

		$this->setRotation($currentYaw + $diff, $this->location->pitch);
	}

	public function attack(EntityDamageEvent $source) : void{
		if(
			$source instanceof EntityDamageByBlockEvent
			&& $source->getDamager() instanceof SweetBerryBush
		){
			$source->cancel();
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
		$this->targetItemEntity = null;
		$this->setRotation($this->location->yaw, 0);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if($item->isNull() && $this->hasHeldItem() && $this->spitDelay <= 0){
			$player->getInventory()->setItemInHand(clone $this->heldItem);
			$this->equipItem(VanillaItems::AIR());
			$this->spitDelay = 20;
			$this->getWorld()->addSound($this->location, new PopSound());
			return true;
		}

		if(!$this->isBerryItem($item)){
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
