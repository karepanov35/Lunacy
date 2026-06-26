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
use pocketmine\entity\Entity;

use pocketmine\entity\passive\Villager;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\Location;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\item\Item;
use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;
use function atan2;
use function cos;
use function floor;
use function mt_rand;
use function rad2deg;
use function sin;
use function sqrt;

class Witch extends Living{

	private ?Living $target = null;
	private int $throwCooldown = 0;
	private int $drinkCooldown = 0;
	private int $idleTime = 0;
	private ?Vector3 $wanderTarget = null;
	private float $smoothMotionX = 0.0;
	private float $smoothMotionZ = 0.0;

	private Item $heldItem;
	private int $heldItemTicks = 0;
	private int $prepareThrowTicks = 0;

	private static array $attackPotionTypes = [
		PotionType::HARMING,
		PotionType::STRONG_HARMING,
		PotionType::POISON,
		PotionType::SLOWNESS,
		PotionType::WEAKNESS,
	];

	public static function getNetworkTypeId() : string{
		return EntityIds::WITCH;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.95, 0.6);
	}

	public function getName() : string{
		return "Witch";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(26);
		$this->setHealth(26);
		$this->setStepHeight(0.6);
		$this->heldItem = VanillaItems::AIR();
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			return $hasUpdate;
		}

		$this->validateTarget();

		if($this->getHealth() < 8 && $this->drinkCooldown <= 0){
			$this->drinkHealingPotion();
			$this->drinkCooldown = 100;
		}

		if($this->throwCooldown > 0){
			$this->throwCooldown -= $tickDiff;
		}
		if($this->drinkCooldown > 0){
			$this->drinkCooldown -= $tickDiff;
		}

		if($this->heldItemTicks > 0){
			$this->heldItemTicks -= $tickDiff;
			if($this->heldItemTicks <= 0 && !$this->heldItem->isNull()){
				$this->heldItem = VanillaItems::AIR();
				$this->broadcastHeldItem();
			}
		}
		if($this->prepareThrowTicks > 0){
			$this->prepareThrowTicks -= $tickDiff;
		}

		if($this->target === null){
			$this->findNearestTarget();
			$this->wanderAI();
		}else{
			$this->combatAI();
		}

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
		if(!$this->target->isAlive() || $this->target->isClosed()){
			$this->clearPreparationState();
			$this->target = null;
			$this->setTargetEntity(null);
			return;
		}
		if($this->target instanceof Player && $this->target->isCreative()){
			$this->clearPreparationState();
			$this->target = null;
			$this->setTargetEntity(null);
			return;
		}
		if($this->location->distanceSquared($this->target->getPosition()) > 576){
			$this->clearPreparationState();
			$this->target = null;
			$this->setTargetEntity(null);
		}
	}

	private function clearPreparationState() : void{
		$this->prepareThrowTicks = 0;
		if(!$this->heldItem->isNull()){
			$this->heldItem = VanillaItems::AIR();
			$this->broadcastHeldItem();
		}
	}

	private function findNearestTarget() : void{
		$nearest = null;
		$nearestDist = 576.0;
		foreach($this->getWorld()->getEntities() as $entity){
			if($entity instanceof Player){
				if(!$entity->isAlive() || $entity->isCreative()){
					continue;
				}
			}elseif($entity instanceof Villager){
			}else{
				continue;
			}
			$dist = $this->location->distanceSquared($entity->getPosition());
			if($dist < $nearestDist && $this->canSee($entity)){
				$nearestDist = $dist;
				$nearest = $entity;
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

		$lookY = $targetPos->y + 1.0;
		$this->lookAt(new Vector3($targetPos->x, $lookY, $targetPos->z));
		$pitch = max(-30.0, min(30.0, $this->location->pitch));
		$this->setRotation($this->location->yaw, $pitch);

		if($dist < 4.0){
			$dx = ($this->location->x - $targetPos->x) / ($dist > 0.01 ? $dist : 1);
			$dz = ($this->location->z - $targetPos->z) / ($dist > 0.01 ? $dist : 1);
			$speed = 0.12;
			$this->smoothMotionX = $this->smoothMotionX * 0.8 + $dx * $speed * 0.2;
			$this->smoothMotionZ = $this->smoothMotionZ * 0.8 + $dz * $speed * 0.2;
		}elseif($dist > 12.0){
			$dx = ($targetPos->x - $this->location->x) / $dist;
			$dz = ($targetPos->z - $this->location->z) / $dist;
			$speed = 0.18;
			$this->smoothMotionX = $this->smoothMotionX * 0.6 + $dx * $speed * 0.4;
			$this->smoothMotionZ = $this->smoothMotionZ * 0.6 + $dz * $speed * 0.4;
		}else{
			$this->smoothMotionX *= 0.85;
			$this->smoothMotionZ *= 0.85;
		}

		if($this->throwCooldown <= 0 && $this->canSee($this->target) && $dist >= 4.5 && $dist <= 14.0 && $this->prepareThrowTicks <= 0){
			if(!$this->heldItem->isNull()){
				$this->throwPotion();
				$this->throwCooldown = 45;
				$this->heldItem = VanillaItems::AIR();
				$this->heldItemTicks = 10;
				$this->broadcastHeldItem();
			}else{
				$potionType = self::$attackPotionTypes[array_rand(self::$attackPotionTypes)];
				$this->heldItem = VanillaItems::SPLASH_POTION()->setType($potionType);
				$this->broadcastHeldItem();
				$this->prepareThrowTicks = 40;
			}
		}
	}

	private function throwPotion() : void{
		if($this->target === null){
			return;
		}
		$potionType = ($this->heldItem instanceof \pocketmine\item\SplashPotion)
			? $this->heldItem->getType()
			: self::$attackPotionTypes[array_rand(self::$attackPotionTypes)];
		$from = $this->getEyePos();
		$targetPos = $this->target->getPosition();
		$targetY = $targetPos->y + 1.0;
		$diffX = $targetPos->x - $from->x;
		$diffY = ($targetY - $from->y) + 0.2;
		$diffZ = $targetPos->z - $from->z;
		$dist = sqrt($diffX * $diffX + $diffY * $diffY + $diffZ * $diffZ);
		if($dist < 0.1){
			return;
		}
		$motion = (new Vector3($diffX, $diffY, $diffZ))->normalize()->multiply(0.6);
		$potion = new SplashPotion(
			Location::fromObject($from, $this->getWorld(), $this->location->yaw, $this->location->pitch),
			$this,
			$potionType,
			null
		);
		$potion->setMotion($motion);
		$potion->spawnToAll();
		$this->getWorld()->addSound($this->location, new ThrowSound());
		$this->broadcastAnimation(new ArmSwingAnimation($this));
	}

	private function drinkHealingPotion() : void{
		$this->getEffects()->add(new EffectInstance(VanillaEffects::REGENERATION(), 100, 1));
		$this->getEffects()->add(new EffectInstance(VanillaEffects::RESISTANCE(), 100, 0));
	}

	private function wanderAI() : void{
		$this->idleTime--;
		if($this->wanderTarget !== null && $this->location->distanceSquared($this->wanderTarget) < 4){
			$this->wanderTarget = null;
		}
		if($this->wanderTarget === null && $this->idleTime <= 0){
			$this->idleTime = 80;
			$angle = mt_rand(0, 359) * (M_PI / 180);
			$r = mt_rand(4, 10);
			$this->wanderTarget = $this->location->add(cos($angle) * $r, 0, sin($angle) * $r);
		}
		if($this->wanderTarget !== null){
			$x = $this->wanderTarget->x - $this->location->x;
			$z = $this->wanderTarget->z - $this->location->z;
			$len = sqrt($x * $x + $z * $z);
			if($len > 0.05){
				$speed = 0.08;
				$this->smoothMotionX = $this->smoothMotionX * 0.7 + ($x / $len) * $speed * 0.3;
				$this->smoothMotionZ = $this->smoothMotionZ * 0.7 + ($z / $len) * $speed * 0.3;
				$this->location->yaw = rad2deg(atan2(-$x, $z));
			}
		}else{
			$this->smoothMotionX *= 0.9;
			$this->smoothMotionZ *= 0.9;
		}
	}

	public function getDrops() : array{
		$drops = [];
		if(mt_rand(0, 99) < 25){
			$drops[] = VanillaItems::GLOWSTONE_DUST()->setCount(mt_rand(0, 2));
		}
		if(mt_rand(0, 99) < 25){
			$drops[] = VanillaItems::SUGAR()->setCount(mt_rand(0, 2));
		}
		if(mt_rand(0, 99) < 25){
			$drops[] = VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2));
		}
		if(mt_rand(0, 99) < 25){
			$drops[] = VanillaItems::SPIDER_EYE()->setCount(mt_rand(0, 2));
		}
		if(mt_rand(0, 99) < 25){
			$drops[] = VanillaItems::GLASS_BOTTLE()->setCount(mt_rand(0, 2));
		}
		if(mt_rand(0, 99) < 15){
			$drops[] = VanillaItems::STICK()->setCount(mt_rand(0, 2));
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::WITCH_SPAWN_EGG();
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$this->sendHeldItemTo($player);
	}

	private function broadcastHeldItem() : void{
		foreach($this->getViewers() as $viewer){
			$this->sendHeldItemTo($viewer);
		}
	}

	private function sendHeldItemTo(Player $player) : void{
		$session = $player->getNetworkSession();
		$wrapper = ItemStackWrapper::legacy($session->getTypeConverter()->coreItemStackToNet($this->heldItem));
		$session->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			$wrapper,
			0,
			0,
			ContainerIds::INVENTORY
		));
	}
}
