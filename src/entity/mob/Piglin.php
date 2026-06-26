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

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
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
use pocketmine\world\sound\CrossbowShootSound;
use pocketmine\world\sound\PopSound;
use function array_rand;
use function cos;
use function deg2rad;
use function max;
use function min;
use function mt_rand;
use function sin;
use function sqrt;

class Piglin extends Monster{
	private const BARTER_TICKS = 40;
	private const GOLD_SCAN_COOLDOWN = 20;
	private const GOLD_PICKUP_RANGE_SQ = 3.24;
	private const TARGET_RANGE_SQ = 256.0;
	private const SHOOT_RANGE_SQ = 100.0;
	private const SHOOT_MIN_RANGE_SQ = 9.0;

	private int $barterTicks = 0;
	private int $goldScanCooldown = 0;
	private ?ItemEntity $targetGoldEntity = null;
	private ?Player $combatTarget = null;
	private ?Vector3 $wanderTarget = null;
	private int $idleTime = 0;
	private Item $mainHandItem;
	private Item $offHandItem;
	private int $equipmentResendTicks = 0;
	private bool $lastAngryState = false;
	private bool $isChargingCrossbow = false;
	private int $chargingTicks = 0;
	private bool $lastChargeState = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::PIGLIN;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.95, 0.6);
	}

	public function getName() : string{
		return "Piglin";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->mainHandItem = VanillaItems::CROSSBOW();
		$this->offHandItem = VanillaItems::AIR();
		$this->setMaxHealth(16);
		$this->setHealth(min($this->getHealth(), 16.0));
		$this->setStepHeight(1.0);
		$this->setCanSaveWithChunk(false);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->combatTarget !== null);
		$properties->setGenericFlag(EntityMetadataFlags::CHARGE_ATTACK, $this->isChargingCrossbow);
		$properties->setGenericFlag(EntityMetadataFlags::CHARGED, $this->combatTarget !== null);
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

		if($this->barterTicks > 0){
			$this->barterTicks = max(0, $this->barterTicks - $tickDiff);
			if($this->barterTicks === 0){
				$this->finishBarter();
			}
			$this->updateStateMetadata();
			return true;
		}

		if($this->goldScanCooldown > 0){
			$this->goldScanCooldown = max(0, $this->goldScanCooldown - $tickDiff);
		}

		if($this->handleGoldTarget()){
			$this->handleVanillaJump(0.42);
			$this->tickAiCooldowns($tickDiff);
			$this->updateStateMetadata();
			return true;
		}

		$this->validateCombatTarget();
		if($this->combatTarget === null){
			$this->combatTarget = $this->findCombatTarget();
		}

		if($this->combatTarget !== null){
			$this->tickCombat($this->combatTarget);
		}else{
			$this->tickWander();
		}

		$this->handleVanillaJump(0.42);
		$this->tickAiCooldowns($tickDiff);
		$this->updateStateMetadata();

		return true;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::PIGLIN_SPAWN_EGG();
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$this->sendMainHandItemTo($player);
		$this->sendOffHandItemTo($player);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($this->barterTicks > 0){
			return false;
		}

		$item = $player->getInventory()->getItemInHand();
		if($item->getTypeId() === VanillaItems::GOLD_INGOT()->getTypeId()){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$this->setOffHandItem(VanillaItems::GOLD_INGOT());
			$this->startBarter();
			return true;
		}

		return parent::onInteract($player, $clickPos);
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source->isCancelled() || !$this->isAlive()){
			return;
		}

		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Player){
				$this->combatTarget = $damager;
				$this->setTargetEntity($damager);
				$this->targetGoldEntity = null;
			}
		}
	}

	private function handleGoldTarget() : bool{
		if($this->targetGoldEntity !== null){
			if($this->targetGoldEntity->isClosed() || $this->targetGoldEntity->getWorld() !== $this->getWorld()){
				$this->targetGoldEntity = null;
			}
		}

		if($this->targetGoldEntity !== null){
			$distSq = $this->location->distanceSquared($this->targetGoldEntity->getPosition());
			if($distSq <= self::GOLD_PICKUP_RANGE_SQ){
				$item = $this->targetGoldEntity->getItem();
				if($item->getCount() > 1){
					$this->targetGoldEntity->setStackSize($item->getCount() - 1);
				}else{
					$this->targetGoldEntity->flagForDespawn();
				}
				$this->targetGoldEntity = null;
				$this->setOffHandItem(VanillaItems::GOLD_INGOT());
				$this->startBarter();
				return true;
			}

			$this->combatTarget = null;
			$this->setTargetEntity(null);
			$this->moveTowardsPoint($this->targetGoldEntity->getPosition(), 0.26, true, true);
			return true;
		}

		if($this->goldScanCooldown > 0){
			return false;
		}

		$this->goldScanCooldown = self::GOLD_SCAN_COOLDOWN;
		$nearest = null;
		$nearestDist = 200.0;
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(10, 5, 10), $this) as $entity){
			if(!$entity instanceof ItemEntity){
				continue;
			}
			$item = $entity->getItem();
			if($item->getTypeId() !== VanillaItems::GOLD_INGOT()->getTypeId()){
				continue;
			}
			$distSq = $this->location->distanceSquared($entity->getPosition());
			if($distSq < $nearestDist){
				$nearestDist = $distSq;
				$nearest = $entity;
			}
		}

		if($nearest !== null){
			$this->targetGoldEntity = $nearest;
			return true;
		}

		return false;
	}

	private function findCombatTarget() : ?Player{
		$nearest = null;
		$bestDist = self::TARGET_RANGE_SQ;
		foreach($this->getWorld()->getPlayers() as $player){
			if($player->isCreative() || $player->isSpectator()){
				continue;
			}
			if($this->isWearingGoldArmor($player)){
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

	private function validateCombatTarget() : void{
		if($this->combatTarget === null){
			return;
		}
		if(!$this->combatTarget->isAlive() || $this->combatTarget->isClosed()){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
			return;
		}
		if($this->combatTarget->isCreative() || $this->combatTarget->isSpectator()){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
			return;
		}
		if($this->isWearingGoldArmor($this->combatTarget)){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
			return;
		}
		if($this->location->distanceSquared($this->combatTarget->getPosition()) > self::TARGET_RANGE_SQ){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
		}
	}

	private function tickCombat(Player $target) : void{
		$this->combatTarget = $target;
		$this->setTargetEntity($target);
		$this->lookAt($target->getEyePos());

		$distSq = $this->location->distanceSquared($target->getPosition());

		if($distSq < self::SHOOT_MIN_RANGE_SQ){
			$dist = sqrt($distSq);
			if($dist > 0.01){
				$dx = ($this->location->x - $target->getPosition()->x) / $dist;
				$dz = ($this->location->z - $target->getPosition()->z) / $dist;
				$this->moveTowardsPoint($this->location->add($dx * 2, 0, $dz * 2), 0.24, true, true);
			}
			$this->isChargingCrossbow = false;
			$this->chargingTicks = 0;
		}elseif($distSq <= self::SHOOT_RANGE_SQ){
			$this->motion->x = $this->smoothMotionX *= 0.7;
			$this->motion->z = $this->smoothMotionZ *= 0.7;

			if($this->attackCooldown <= 0){
				if(!$this->isChargingCrossbow){
					$this->isChargingCrossbow = true;
					$this->chargingTicks = 0;
					$this->networkPropertiesDirty = true;
				}

				$this->chargingTicks++;
				if($this->chargingTicks >= 20){
					if($this->shootCrossbow($target)){
						$this->attackCooldown = 35;
					}
					$this->isChargingCrossbow = false;
					$this->chargingTicks = 0;
					$this->networkPropertiesDirty = true;
				}
			}
		}else{
			$this->isChargingCrossbow = false;
			$this->chargingTicks = 0;
			$this->moveTowardsPoint($target->getPosition(), 0.27, true, true);
		}
	}

	private function tickWander() : void{
		$this->combatTarget = null;
		$this->setTargetEntity(null);

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

	private function shootCrossbow(Player $target) : bool{
		if(!$this->shootProjectileAt($target, $this->mainHandItem, 3.15, 0.07)){
			return false;
		}

		$this->getWorld()->addSound($this->location, new CrossbowShootSound());
		$this->broadcastAnimation(new ArmSwingAnimation($this));
		return true;
	}

	private function startBarter() : void{
		$this->combatTarget = null;
		$this->setTargetEntity(null);
		$this->barterTicks = self::BARTER_TICKS;
	}

	private function finishBarter() : void{
		$this->setOffHandItem(VanillaItems::AIR());
		$this->getWorld()->dropItem($this->getLocation(), $this->createBarterLoot());
		$this->getWorld()->addSound($this->getLocation(), new PopSound());
	}

	private function createBarterLoot() : Item{
		$lootTable = [
			fn() => VanillaItems::ENDER_PEARL()->setCount(mt_rand(2, 4)),
			fn() => VanillaItems::FIRE_CHARGE()->setCount(mt_rand(1, 5)),
			fn() => VanillaBlocks::OBSIDIAN()->asItem()->setCount(1),
			fn() => VanillaItems::LEATHER()->setCount(mt_rand(2, 5)),
			fn() => VanillaItems::IRON_INGOT()->setCount(mt_rand(2, 5)),
			fn() => VanillaItems::GLOWSTONE_DUST()->setCount(mt_rand(2, 5)),
			fn() => VanillaItems::STRING()->setCount(mt_rand(4, 12)),
			fn() => VanillaItems::NETHER_QUARTZ()->setCount(mt_rand(4, 12)),
			fn() => VanillaBlocks::GRAVEL()->asItem()->setCount(mt_rand(8, 16))
		];
		return $lootTable[array_rand($lootTable)]();
	}

	private function setOffHandItem(Item $item) : void{
		$this->offHandItem = $item;
		$this->equipmentResendTicks = 5;
		$this->broadcastEquipment();
	}

	private function broadcastEquipment() : void{
		foreach($this->getViewers() as $viewer){
			$this->sendMainHandItemTo($viewer);
			$this->sendOffHandItemTo($viewer);
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

	private function sendOffHandItemTo(Player $player) : void{
		$session = $player->getNetworkSession();
		$wrapper = ItemStackWrapper::legacy($session->getTypeConverter()->coreItemStackToNet($this->offHandItem));
		$session->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			$wrapper,
			0,
			0,
			ContainerIds::OFFHAND
		));
	}

	private function updateStateMetadata() : void{
		$angry = $this->combatTarget !== null;
		if($angry !== $this->lastAngryState){
			$this->lastAngryState = $angry;
			$this->networkPropertiesDirty = true;
		}
		$charging = $this->isChargingCrossbow;
		if($charging !== $this->lastChargeState){
			$this->lastChargeState = $charging;
			$this->networkPropertiesDirty = true;
		}
	}

	private function isWearingGoldArmor(Player $player) : bool{
		foreach($player->getArmorInventory()->getContents() as $item){
			$typeId = $item->getTypeId();
			if($typeId === VanillaItems::GOLDEN_HELMET()->getTypeId()
				|| $typeId === VanillaItems::GOLDEN_CHESTPLATE()->getTypeId()
				|| $typeId === VanillaItems::GOLDEN_LEGGINGS()->getTypeId()
				|| $typeId === VanillaItems::GOLDEN_BOOTS()->getTypeId()){
				return true;
			}
		}
		return false;
	}
}
