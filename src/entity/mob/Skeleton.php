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

namespace pocketmine\entity\mob;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Monster;
use pocketmine\entity\animation\ArmSwingAnimation;
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
use pocketmine\world\sound\BowShootSound;
use function cos;
use function floor;
use function mt_rand;
use function sin;

class Skeleton extends Monster{
	private Item $heldItem;
	private ?Player $target = null;
	private bool $isAiming = false;
	private int $aimingTicks = 0;
	private int $idleTime = 0;
	private ?Vector3 $wanderTarget = null;
	private int $fireTickCooldown = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::SKELETON;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.99, 0.6);
	}

	public function getName() : string{
		return "Skeleton";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->heldItem = VanillaItems::BOW();
		$this->setMaxHealth(20);
		$this->setHealth(20);
		$this->setStepHeight(1.0);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::CHARGE_ATTACK, $this->isAiming);
		$properties->setLong(
			EntityMetadataProperties::TARGET_EID,
			$this->isAiming && $this->target !== null ? $this->target->getId() : -1
		);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			return true;
		}

		$this->validateTarget();
		$this->checkDaylightBurning();

		if($this->target === null){
			$this->findNearestPlayer();
			$this->tickWander();
		}else{
			$this->tickCombat();
		}

		$this->handleVanillaJump(0.5);
		$this->tickAiCooldowns($tickDiff);

		return true;
	}

	private function validateTarget() : void{
		if($this->target === null){
			return;
		}
		if(
			!$this->target->isAlive()
			|| $this->target->isClosed()
			|| $this->target->isCreative()
			|| $this->location->distanceSquared($this->target->getPosition()) > 625
		){
			$this->target = null;
			$this->isAiming = false;
			$this->networkPropertiesDirty = true;
		}
	}

	private function tickCombat() : void{
		if($this->attackTime > 0){
			$this->lookAt($this->target->getEyePos());
			return;
		}

		$targetPos = $this->target->getPosition();
		$distSq = $this->location->distanceSquared($targetPos);
		$this->lookAt($this->target->getEyePos());

		if($distSq < 36){
			$this->moveTowardsPoint(
				$this->location->add($this->location->x - $targetPos->x, 0, $this->location->z - $targetPos->z),
				0.18,
				true,
				true
			);
		}elseif($distSq > 100){
			$this->moveTowardsPoint($targetPos, 0.22, true, true);
		}else{
			$this->smoothMotionX *= 0.6;
			$this->smoothMotionZ *= 0.6;
			$this->motion->x = $this->smoothMotionX;
			$this->motion->z = $this->smoothMotionZ;
		}

		if($this->attackCooldown > 0 || !$this->canSee($this->target)){
			return;
		}

		if(!$this->isAiming){
			$this->isAiming = true;
			$this->networkPropertiesDirty = true;
			$this->aimingTicks = 0;
		}

		$this->aimingTicks++;
		if($this->aimingTicks < 20){
			return;
		}

		if($this->shootProjectileAt($this->target, $this->heldItem, 2.0, 0.07)){
			$this->getWorld()->addSound($this->location, new BowShootSound());
			$this->broadcastAnimation(new ArmSwingAnimation($this));
		}
		$this->isAiming = false;
		$this->attackCooldown = 40;
		$this->networkPropertiesDirty = true;
	}

	private function tickWander() : void{
		if($this->idleTime > 0){
			$this->idleTime--;
			$this->motion->x = $this->smoothMotionX *= 0.85;
			$this->motion->z = $this->smoothMotionZ *= 0.85;
			return;
		}

		if($this->wanderTarget === null || $this->location->distanceSquared($this->wanderTarget) < 9){
			$this->idleTime = 80;
			$angle = mt_rand(0, 359) * (M_PI / 180);
			$dist = mt_rand(6, 12);
			$this->wanderTarget = $this->location->add(cos($angle) * $dist, 0, sin($angle) * $dist);
		}

		$this->moveTowardsPoint($this->wanderTarget, 0.22, true, true);
	}

	public function getDrops() : array{
		return [
			VanillaItems::BONE()->setCount(mt_rand(1, 2)),
			VanillaItems::ARROW()->setCount(mt_rand(0, 2)),
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	private function checkDaylightBurning() : void{
		if($this->fireTickCooldown > 0){
			$this->fireTickCooldown--;
			return;
		}
		if($this->getWorld()->getTimeOfDay() >= 12000 || $this->isUnderwater()){
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
			if(!$player->isAlive() || $player->isCreative()){
				continue;
			}
			if($this->location->distanceSquared($player->getPosition()) >= 300){
				continue;
			}
			if($this->canSee($player)){
				$this->target = $player;
				break;
			}
		}
	}

	private function canSee(Entity $entity) : bool{
		$start = $this->getEyePos();
		$end = $entity->getEyePos();
		$dir = $end->subtractVector($start)->normalize();
		$steps = (int) floor($start->distance($end) / 0.8);
		for($i = 0; $i < $steps; $i++){
			$pos = $start->addVector($dir->multiply($i * 0.8));
			if($this->getWorld()->getBlockAt((int) $pos->x, (int) $pos->y, (int) $pos->z)->isSolid()){
				return false;
			}
		}
		return true;
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$player->getNetworkSession()->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($player->getNetworkSession()->getTypeConverter()->coreItemStackToNet($this->heldItem)),
			0,
			0,
			ContainerIds::INVENTORY
		));
	}
}
