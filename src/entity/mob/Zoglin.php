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
use pocketmine\entity\Ageable;

use pocketmine\entity\ai\Goal;
use pocketmine\entity\ai\GoalExecutor;
use pocketmine\entity\ai\Sensor;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\sound\ZoglinAlertSound;
use function cos;
use function deg2rad;
use function max;
use function min;
use function mt_rand;
use function sin;

class Zoglin extends Monster implements Ageable{
	private const TAG_AGE = "Age";
	private const TAG_AGE_LOCKED = "AgeLocked";
	private const BABY_AGE = -24000;

	private int $age = 0;
	private bool $ageLocked = false;

	private ?Living $combatTarget = null;
	private ?Vector3 $wanderTarget = null;
	private int $idleTime = 0;
	private int $aggressionSoundCooldown = 0;
	private bool $lastBabyState = false;

	private GoalExecutor $goalExecutor;

	public static function getNetworkTypeId() : string{
		return EntityIds::ZOGLIN;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.4, 0.9);
	}

	public function getName() : string{
		return "Zoglin";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(40);
		$this->setHealth(min($this->getHealth(), 40.0));
		$this->setStepHeight(1.0);

		$this->age = $nbt->getInt(self::TAG_AGE, 0);
		$this->ageLocked = $nbt->getByte(self::TAG_AGE_LOCKED, 0) !== 0;
		$this->lastBabyState = $this->isBaby();
		$this->goalExecutor = $this->createGoalExecutor();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setByte(self::TAG_AGE_LOCKED, $this->ageLocked ? 1 : 0);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, true);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if(!$this->ageLocked && $this->age < 0){
			$this->age = min(0, $this->age + $tickDiff);
		}

		if($this->tickWaterLock($tickDiff, 0.03, -0.24)){
			$this->updateStateMetadata();
			return true;
		}
		if($this->tickPostHitMovementPause($tickDiff)){
			$this->tickAiCooldowns($tickDiff);
			$this->updateStateMetadata();
			return true;
		}

		$this->validateCombatTarget();
		$this->goalExecutor->tick($this, $tickDiff);
		$this->handleVanillaJump(0.43);
		$this->tickAiCooldowns($tickDiff);

		if($this->aggressionSoundCooldown > 0){
			$this->aggressionSoundCooldown = max(0, $this->aggressionSoundCooldown - $tickDiff);
		}
		$this->updateStateMetadata();

		return true;
	}

	public function isFireProof() : bool{
		return true;
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function setBaby(bool $baby = true) : void{
		$this->age = $baby ? self::BABY_AGE : 0;
	}

	public function setAgeLocked(bool $locked) : void{
		$this->ageLocked = $locked;
	}

	public function tickCombat(Living $target) : void{
		if(!$this->canTargetEntity($target)){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
			return;
		}

		if($this->combatTarget !== $target && $this->aggressionSoundCooldown <= 0){
			$this->getWorld()->addSound($this->location, new ZoglinAlertSound($this));
			$this->aggressionSoundCooldown = 30;
		}

		$this->combatTarget = $target;
		$this->setTargetEntity($target);
		$this->lookAt($target->getEyePos());

		$distSq = $this->location->distanceSquared($target->getPosition());
		if($distSq <= 3.24){
			$this->motion->x = $this->smoothMotionX *= 0.55;
			$this->motion->z = $this->smoothMotionZ *= 0.55;
			$this->attackLivingTarget($target);
			return;
		}

		$this->moveTowardsPoint($target->getPosition(), $this->isBaby() ? 0.24 : 0.31, true, true);
	}

	public function tickWander() : void{
		if($this->idleTime > 0){
			$this->idleTime--;
			$this->motion->x = $this->smoothMotionX *= 0.84;
			$this->motion->z = $this->smoothMotionZ *= 0.84;
			return;
		}

		if($this->wanderTarget === null || $this->location->distanceSquared($this->wanderTarget) < 6.0){
			$angle = mt_rand(0, 359);
			$dist = mt_rand(4, 11);
			$rad = deg2rad((float) $angle);
			$this->wanderTarget = $this->location->add(cos($rad) * $dist, 0, sin($rad) * $dist);
			$this->idleTime = mt_rand(8, 40);
		}

		$this->combatTarget = null;
		$this->setTargetEntity(null);
		$this->moveTowardsPoint($this->wanderTarget, $this->isBaby() ? 0.2 : 0.24, true, true);
	}

	public function detectCombatTarget(float $range) : ?Living{
		if($this->combatTarget !== null && $this->canTargetEntity($this->combatTarget) && $this->location->distanceSquared($this->combatTarget->getPosition()) <= $range * $range){
			return $this->combatTarget;
		}

		$bb = new AxisAlignedBB(
			$this->location->x - $range,
			$this->location->y - 4,
			$this->location->z - $range,
			$this->location->x + $range,
			$this->location->y + 4,
			$this->location->z + $range
		);
		$best = null;
		$bestDist = $range * $range;
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!$entity instanceof Living || !$this->canTargetEntity($entity)){
				continue;
			}
			$distSq = $this->location->distanceSquared($entity->getPosition());
			if($distSq < $bestDist){
				$bestDist = $distSq;
				$best = $entity;
			}
		}

		return $best;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source->isCancelled() || !$this->isAlive()){
			return;
		}
		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Living && $this->canTargetEntity($damager)){
				$this->combatTarget = $damager;
				$this->setTargetEntity($damager);
				$this->alertNearbyZoglins($damager);
			}
		}
	}

	public function getDrops() : array{
		if($this->isBaby()){
			return [];
		}
		return [VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(1, 3))];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ZOGLIN_SPAWN_EGG();
	}

	private function createGoalExecutor() : GoalExecutor{
		return new GoalExecutor(
			[
				new class implements Sensor{
					public function collect(Living $entity, array &$memory) : void{
						if($entity instanceof Zoglin){
							$cooldown = (int) ($memory["target_scan_cooldown"] ?? 0);
							if($cooldown > 0){
								$memory["target_scan_cooldown"] = $cooldown - 1;
								return;
							}
							$memory["target"] = $entity->detectCombatTarget(16.0);
							$memory["target_scan_cooldown"] = 2;
						}
					}
				}
			],
			[
				new class implements Goal{
					public function getPriority() : int{
						return 90;
					}

					public function canRun(Living $entity, array $memory) : bool{
						return $entity instanceof Zoglin && ($memory["target"] ?? null) instanceof Living;
					}

					public function tick(Living $entity, array $memory, int $tickDiff) : void{
						if($entity instanceof Zoglin && ($memory["target"] ?? null) instanceof Living){
							$entity->tickCombat($memory["target"]);
						}
					}
				},
				new class implements Goal{
					public function getPriority() : int{
						return 10;
					}

					public function canRun(Living $entity, array $memory) : bool{
						return $entity instanceof Zoglin;
					}

					public function tick(Living $entity, array $memory, int $tickDiff) : void{
						if($entity instanceof Zoglin){
							$entity->tickWander();
						}
					}
				}
			]
		);
	}

	private function attackLivingTarget(Living $target) : void{
		if($this->attackCooldown > 0){
			return;
		}

		$this->broadcastAnimation(new ArmSwingAnimation($this));
		$damage = $this->isBaby() ? 3.0 : 6.0;
		$ev = new EntityDamageByEntityEvent(
			$this,
			$target,
			EntityDamageEvent::CAUSE_ENTITY_ATTACK,
			$damage,
			[],
			$this->isBaby() ? 0.45 : 0.9,
			0.55
		);
		$target->attack($ev);
		$this->attackCooldown = 20;
	}

	private function updateStateMetadata() : void{
		$baby = $this->isBaby();
		if($baby !== $this->lastBabyState){
			$this->lastBabyState = $baby;
			$this->networkPropertiesDirty = true;
		}
	}

	private function validateCombatTarget() : void{
		if($this->combatTarget === null){
			return;
		}
		if(!$this->canTargetEntity($this->combatTarget) || $this->location->distanceSquared($this->combatTarget->getPosition()) > 324){
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

		$typeId = $entity::getNetworkTypeId();
		if($typeId === EntityIds::ZOGLIN || $typeId === EntityIds::CREEPER || $typeId === EntityIds::GHAST){
			return false;
		}

		return true;
	}

	private function alertNearbyZoglins(Living $target) : void{
		$range = 16.0;
		$bb = new AxisAlignedBB(
			$this->location->x - $range,
			$this->location->y - 4,
			$this->location->z - $range,
			$this->location->x + $range,
			$this->location->y + 4,
			$this->location->z + $range
		);

		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!$entity instanceof Zoglin || !$entity->isAlive() || $entity->isClosed()){
				continue;
			}
			if(!$entity->canTargetEntity($target)){
				continue;
			}
			$entity->combatTarget = $target;
			$entity->setTargetEntity($target);
		}
	}
}

