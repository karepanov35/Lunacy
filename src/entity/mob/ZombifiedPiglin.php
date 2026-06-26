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

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Monster;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use function cos;
use function deg2rad;
use function max;
use function min;
use function mt_rand;
use function sin;

class ZombifiedPiglin extends Monster{
	private const TAG_ANGER = "Anger";
	private const ANGER_DURATION = 2400;
	private const ALERT_RANGE = 20.0;
	private const TARGET_RANGE_SQ = 256.0;
	private const ATTACK_RANGE_SQ = 4.0;

	private int $angerTicks = 0;
	private ?Living $combatTarget = null;
	private ?Vector3 $wanderTarget = null;
	private int $idleTime = 0;
	private bool $lastAngryState = false;
	private Item $mainHandItem;
	private int $equipmentResendTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::ZOMBIE_PIGMAN;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	public function getName() : string{
		return "Zombified Piglin";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->mainHandItem = VanillaItems::GOLDEN_SWORD();
		$this->angerTicks = max(0, $nbt->getInt(self::TAG_ANGER, 0));
		$this->setMaxHealth(20);
		$this->setHealth(min($this->getHealth(), 20.0));
		$this->setStepHeight(1.0);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_ANGER, $this->angerTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->angerTicks > 0);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if($this->equipmentResendTicks > 0){
			$this->equipmentResendTicks = max(0, $this->equipmentResendTicks - $tickDiff);
			$this->broadcastEquipment();
		}

		if($this->tickPostHitMovementPause($tickDiff)){
			$this->tickAiCooldowns($tickDiff);
			$this->updateStateMetadata();
			return true;
		}

		if($this->angerTicks > 0){
			$this->angerTicks = max(0, $this->angerTicks - $tickDiff);
		}

		if($this->isAngry()){
			$this->validateCombatTarget();
			if($this->combatTarget === null){
				$this->combatTarget = $this->findNearestPlayerTarget();
			}
			if($this->combatTarget !== null){
				$this->tickCombat($this->combatTarget);
			}else{
				$this->tickWander();
			}
		}else{
			$this->combatTarget = null;
			$this->setTargetEntity(null);
			$this->tickWander();
		}

		$this->handleVanillaJump(0.42);
		$this->tickAiCooldowns($tickDiff);
		$this->updateStateMetadata();

		return true;
	}

	public function isFireProof() : bool{
		return true;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source->isCancelled() || !$this->isAlive()){
			return;
		}

		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Living && $this->canTargetEntity($damager)){
				$this->provoke($damager);
				$this->alertNearby($damager);
			}
		}
	}

	public function provoke(Living $target, int $ticks = self::ANGER_DURATION) : void{
		$this->angerTicks = max($this->angerTicks, $ticks);
		$this->combatTarget = $target;
		$this->setTargetEntity($target);
	}

	public function getDrops() : array{
		$drops = [];
		if(mt_rand(1, 100) <= 75){
			$drops[] = VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(0, 1));
		}
		if(mt_rand(1, 100) <= 25){
			$drops[] = VanillaItems::GOLD_NUGGET();
		}
		if(mt_rand(1, 100) <= 3){
			$drops[] = VanillaItems::GOLD_INGOT();
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return mt_rand(5, 5);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ZOMBIE_PIGMAN_SPAWN_EGG();
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$this->sendMainHandItemTo($player);
	}

	private function broadcastEquipment() : void{
		foreach($this->getViewers() as $viewer){
			$this->sendMainHandItemTo($viewer);
		}
	}

	private function sendMainHandItemTo(Player $player) : void{
		$session = $player->getNetworkSession();
		$wrapper = ItemStackWrapper::legacy($session->getTypeConverter()->coreItemStackToNet($this->mainHandItem));
		$session->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			$wrapper,
			0,
			0,
			ContainerIds::INVENTORY
		));
	}

	private function isAngry() : bool{
		return $this->angerTicks > 0;
	}

	private function tickCombat(Living $target) : void{
		if(!$this->canTargetEntity($target)){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
			return;
		}

		$this->combatTarget = $target;
		$this->setTargetEntity($target);
		$this->lookAt($target->getEyePos());

		$distSq = $this->location->distanceSquared($target->getPosition());
		if($distSq <= self::ATTACK_RANGE_SQ){
			$this->motion->x = $this->smoothMotionX *= 0.6;
			$this->motion->z = $this->smoothMotionZ *= 0.6;
			$this->attackLivingTarget($target);
			return;
		}

		$this->moveTowardsPoint($target->getPosition(), 0.27, true, true);
	}

	private function tickWander() : void{
		if($this->idleTime > 0){
			$this->idleTime--;
			$this->motion->x = $this->smoothMotionX *= 0.82;
			$this->motion->z = $this->smoothMotionZ *= 0.82;
			return;
		}

		if($this->wanderTarget === null || $this->location->distanceSquared($this->wanderTarget) < 6.0){
			$angle = mt_rand(0, 359);
			$dist = mt_rand(4, 10);
			$rad = deg2rad((float) $angle);
			$this->wanderTarget = $this->location->add(cos($rad) * $dist, 0, sin($rad) * $dist);
			$this->idleTime = mt_rand(8, 40);
		}

		$this->moveTowardsPoint($this->wanderTarget, 0.23, true, true);
	}

	private function findNearestPlayerTarget() : ?Player{
		$nearest = null;
		$bestDist = self::TARGET_RANGE_SQ;
		foreach($this->getWorld()->getPlayers() as $player){
			if($player->isCreative() || $player->isSpectator()){
				continue;
			}
			$distSq = $this->location->distanceSquared($player->getPosition());
			if($distSq < $bestDist){
				$bestDist = $distSq;
				$nearest = $player;
			}
		}
		return $nearest;
	}

	private function attackLivingTarget(Living $target) : void{
		if($this->attackCooldown > 0){
			return;
		}

		$this->broadcastAnimation(new ArmSwingAnimation($this));
		$ev = new EntityDamageByEntityEvent(
			$this,
			$target,
			EntityDamageEvent::CAUSE_ENTITY_ATTACK,
			5.0
		);
		$target->attack($ev);
		$this->attackCooldown = 20;
	}

	private function validateCombatTarget() : void{
		if($this->combatTarget === null){
			return;
		}
		if(!$this->canTargetEntity($this->combatTarget) || $this->location->distanceSquared($this->combatTarget->getPosition()) > self::TARGET_RANGE_SQ){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
		}
	}

	private function canTargetEntity(Living $entity) : bool{
		if($entity === $this || !$entity->isAlive() || $entity->isClosed()){
			return false;
		}

		if($entity instanceof Player){
			return !$entity->isCreative() && !$entity->isSpectator();
		}

		return true;
	}

	private function alertNearby(Living $target) : void{
		$range = self::ALERT_RANGE;
		$bb = new AxisAlignedBB(
			$this->location->x - $range,
			$this->location->y - 4,
			$this->location->z - $range,
			$this->location->x + $range,
			$this->location->y + 4,
			$this->location->z + $range
		);

		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!$entity instanceof ZombifiedPiglin || !$entity->isAlive() || $entity->isClosed()){
				continue;
			}
			if(!$entity->canTargetEntity($target)){
				continue;
			}
			$entity->provoke($target);
		}
	}

	private function updateStateMetadata() : void{
		$angry = $this->isAngry();
		if($angry !== $this->lastAngryState){
			$this->lastAngryState = $angry;
			$this->networkPropertiesDirty = true;
		}
	}
}
