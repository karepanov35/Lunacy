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

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\sound\IronGolemCrackSound;
use function abs;
use function atan2;
use function cos;
use function floor;
use function mt_rand;
use function rad2deg;
use function sin;
use function sqrt;

class IronGolem extends Living{

	private ?Living $target = null;
	private int $attackCooldown = 0;
	private int $idleTime = 0;
	private ?Vector3 $wanderTarget = null;
	private int $obstacleAvoidCooldown = 0;
	private float $smoothMotionX = 0.0;
	private float $smoothMotionZ = 0.0;
	/** Уровень трещин (0–3), растёт с каждым ударом. */
	private int $crackLevel = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::IRON_GOLEM;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.7, 1.4);
	}

	public function getName() : string{
		return "Iron Golem";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(100);
		$this->setHealth(100);
		$this->setStepHeight(1.0);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->crackLevel);
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if(!$source->isCancelled() && $this->isAlive()){
			$this->crackLevel = min(3, $this->crackLevel + 1);
			$this->networkPropertiesDirty = true;
			$this->getWorld()->addSound($this->location, new IronGolemCrackSound());
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			return $hasUpdate;
		}

		$this->validateTarget();

		if($this->target === null){
			$this->findNearestHostile();
			$this->wanderAI();
		}else{
			$this->combatAI();
		}

		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}

		return true;
	}

	private function validateTarget() : void{
		if($this->target !== null){
			if(
				!$this->target->isAlive() ||
				$this->target->isClosed() ||
				$this->location->distanceSquared($this->target->getPosition()) > 400
			){
				$this->target = null;
				$this->setTargetEntity(null);
			}
		}
	}

	private function findNearestHostile() : void{
		$world = $this->getWorld();
		$nearest = null;
		$nearestDist = 400.0;
		foreach($world->getEntities() as $entity){
			if(
				$entity instanceof Zombie ||
				$entity instanceof Skeleton ||
				$entity instanceof WitherSkeleton ||
				$entity instanceof Creeper ||
				$entity instanceof Blaze
			){
				if(!$entity->isAlive() || $entity->isClosed()){
					continue;
				}
				$dist = $this->location->distanceSquared($entity->getPosition());
				if($dist < $nearestDist && $this->canSee($entity)){
					$nearestDist = $dist;
					$nearest = $entity;
				}
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
		if($this->attackTime > 0){
			$this->lookAt($this->target->getEyePos());
			return;
		}

		$targetPos = $this->target->getPosition();
		$distSq = $this->location->distanceSquared($targetPos);

		$this->lookAt($this->target->getEyePos());

		if($distSq <= 4.0){
			$this->smoothMotionX *= 0.6;
			$this->smoothMotionZ *= 0.6;
			$this->motion->x = $this->smoothMotionX;
			$this->motion->z = $this->smoothMotionZ;
			$this->attackTarget();
			return;
		}

		$x = $targetPos->x - $this->location->x;
		$z = $targetPos->z - $this->location->z;
		$diff = abs($x) + abs($z);
		if($diff < 0.1){
			$this->motion->x = $this->smoothMotionX *= 0.6;
			$this->motion->z = $this->smoothMotionZ *= 0.6;
			return;
		}

		$speed = 0.22;
		$wantX = $speed * ($x / $diff);
		$wantZ = $speed * ($z / $diff);

		$this->smoothMotionX = $this->smoothMotionX * 0.5 + $wantX * 0.5;
		$this->smoothMotionZ = $this->smoothMotionZ * 0.5 + $wantZ * 0.5;
		$this->motion->x = $this->smoothMotionX;
		$this->motion->z = $this->smoothMotionZ;
	}

	private function attackTarget() : void{
		if($this->attackCooldown > 0 || $this->target === null){
			return;
		}
		$this->broadcastAnimation(new ArmSwingAnimation($this));

		$damage = 10.0;
		$ev = new EntityDamageByEntityEvent($this, $this->target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
		$this->target->attack($ev);

		// лёгкий подброс вверх, как в ванилле
		$motion = $this->target->getMotion();
		$this->target->setMotion(new Vector3($motion->x, $motion->y + 0.5, $motion->z));

		$this->attackCooldown = 30;
	}

	private function wanderAI() : void{
		if($this->idleTime > 0){
			$this->idleTime--;
			$this->motion->x = $this->smoothMotionX *= 0.85;
			$this->motion->z = $this->smoothMotionZ *= 0.85;
			return;
		}

		$motionLen = sqrt($this->motion->x ** 2 + $this->motion->z ** 2);
		if($this->wanderTarget !== null && $motionLen > 0.03){
			$dirX = $this->motion->x / $motionLen;
			$dirZ = $this->motion->z / $motionLen;
			if($this->hasObstacleInFront($dirX, $dirZ, 0.7)){
				$avoid = $this->getAvoidanceDirection($dirX, $dirZ);
				if($avoid !== null){
					$this->wanderTarget = $this->location->add($avoid->x * 6, 0, $avoid->z * 6);
					$this->obstacleAvoidCooldown = 25;
					$this->idleTime = 5;
				}
			}
		}

		if($this->wanderTarget === null || $this->location->distanceSquared($this->wanderTarget) < 9){
			$this->idleTime = 80;
			$angle = mt_rand(0, 359) * (M_PI / 180);
			$dist = mt_rand(6, 12);
			$this->wanderTarget = $this->location->add(cos($angle) * $dist, 0, sin($angle) * $dist);
		}

		$this->moveTowards($this->wanderTarget, true);
	}

	private function hasObstacleInFront(float $dirX, float $dirZ, float $distance = 0.8) : bool{
		$len = sqrt($dirX * $dirX + $dirZ * $dirZ);
		if($len < 0.01){
			return false;
		}
		$dirX /= $len;
		$dirZ /= $len;
		$world = $this->getWorld();
		$x = (int) floor($this->location->x + $dirX * $distance);
		$z = (int) floor($this->location->z + $dirZ * $distance);
		$yFoot = (int) floor($this->location->y);
		$yHead = (int) floor($this->location->y + 2.2);
		return $world->getBlockAt($x, $yFoot, $z)->isSolid() && $world->getBlockAt($x, $yHead, $z)->isSolid();
	}

	private function getAvoidanceDirection(float $dirX, float $dirZ) : ?Vector3{
		$len = sqrt($dirX * $dirX + $dirZ * $dirZ);
		if($len < 0.01){
			return null;
		}
		$dirX /= $len;
		$dirZ /= $len;
		$leftX = -$dirZ;
		$leftZ = $dirX;
		$rightX = $dirZ;
		$rightZ = -$dirX;
		if(!$this->hasObstacleInFront($leftX, $leftZ, 0.9)){
			return new Vector3($leftX, 0, $leftZ);
		}
		if(!$this->hasObstacleInFront($rightX, $rightZ, 0.9)){
			return new Vector3($rightX, 0, $rightZ);
		}
		return null;
	}

	private function moveTowards(Vector3 $target, bool $smooth = true) : void{
		$x = $target->x - $this->location->x;
		$z = $target->z - $this->location->z;
		$diff = abs($x) + abs($z);
		if($diff < 0.1){
			if($smooth){
				$this->motion->x = $this->smoothMotionX *= 0.6;
				$this->motion->z = $this->smoothMotionZ *= 0.6;
			}
			return;
		}
		$speed = 0.18;
		$wantX = $speed * ($x / $diff);
		$wantZ = $speed * ($z / $diff);
		if($smooth){
			$this->smoothMotionX = $this->smoothMotionX * 0.5 + $wantX * 0.5;
			$this->smoothMotionZ = $this->smoothMotionZ * 0.5 + $wantZ * 0.5;
			$this->motion->x = $this->smoothMotionX;
			$this->motion->z = $this->smoothMotionZ;
		}else{
			$this->motion->x = $wantX;
			$this->motion->z = $wantZ;
		}
		if($this->target === null){
			$targetYaw = rad2deg(atan2(-$x, $z));
			$this->location->yaw = $this->lerpAngle($this->location->yaw, $targetYaw, 0.15);
		}
	}

	private function lerpAngle(float $from, float $to, float $t) : float{
		$diff = $to - $from + 540.0;
		$wrapped = $diff - 360.0 * floor($diff / 360.0);
		$delta = $wrapped - 180.0;
		return $from + $delta * $t;
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::IRON_INGOT()->setCount(mt_rand(3, 5))
		];
		if(mt_rand(0, 100) < 25){
			$drops[] = VanillaBlocks::POPPY()->asItem()->setCount(mt_rand(0, 2));
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return 15;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::IRON_GOLEM_SPAWN_EGG();
	}
}

