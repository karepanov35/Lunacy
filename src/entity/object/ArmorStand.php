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
namespace pocketmine\entity\object;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\lang\KnownTranslationKeys;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\world\particle\ArmorStandDestroyParticle;
use pocketmine\world\sound\ArmorStandSound;
use pocketmine\world\sound\ArmorStandSoundType;
use function min;

class ArmorStand extends Living{

	private const TAG_MAINHAND = "Mainhand";
	private const TAG_OFFHAND = "Offhand";
	private const TAG_POSE_INDEX = "PoseIndex";
	private const TAG_ARMOR = "Armor";

	private const SLOT_MAINHAND = -1;
	private const SLOT_OFFHAND = -2;

	private const POSE_COUNT = 13;
	private const VIBRATE_TICKS = 8;

	protected ?Item $itemInHand = null;
	protected ?Item $offhandItem = null;
	protected int $poseIndex = 0;
	protected int $vibrateTimer = 0;

	public static function getNetworkTypeId() : string{ return EntityIds::ARMOR_STAND; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(1.975, 0.5); }

	protected function getInitialGravity() : float{ return 0.04; }

	public function getName() : string{ return "Armor Stand"; }

	public function getPickedItem() : ?Item{
		return VanillaItems::ARMOR_STAND();
	}

	public function getFrostWalkerLevel() : int{
		return 0;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(6);
		$this->setHealth(6);
		$this->setNoClientPredictions();
		parent::initEntity($nbt);

		$armorTag = $nbt->getListTag(self::TAG_ARMOR);
		if($armorTag !== null){
			/** @var CompoundTag $armorItem */
			foreach($armorTag as $armorItem){
				$slot = $armorItem->getByte("Slot", -1);
				if($slot < ArmorInventory::SLOT_HEAD || $slot > ArmorInventory::SLOT_FEET){
					continue;
				}
				$this->armorInventory->setItem($slot, Item::nbtDeserialize($armorItem));
			}
		}

		$mainhandTag = $nbt->getCompoundTag(self::TAG_MAINHAND);
		if($mainhandTag !== null){
			$this->itemInHand = Item::nbtDeserialize($mainhandTag);
		}

		$offhandTag = $nbt->getCompoundTag(self::TAG_OFFHAND);
		if($offhandTag !== null){
			$this->offhandItem = Item::nbtDeserialize($offhandTag);
		}

		$this->setPoseIndex(min($nbt->getInt(self::TAG_POSE_INDEX, 0), self::POSE_COUNT - 1));
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setGenericFlag(EntityMetadataFlags::NO_AI, true);
		$properties->setInt(EntityMetadataProperties::ARMOR_STAND_POSE_INDEX, $this->poseIndex);
		$properties->setString(EntityMetadataProperties::INTERACTIVE_TAG, KnownTranslationKeys::ACTION_INTERACT_ARMORSTAND_EQUIP);
	}

	public function setPoseIndex(int $pose) : void{
		$this->poseIndex = $pose;
		$this->networkPropertiesDirty = true;
	}

	public function getPoseIndex() : int{
		return $this->poseIndex;
	}

	public function getItemInHand() : Item{
		return $this->itemInHand ?? VanillaItems::AIR();
	}

	public function setItemInHand(Item $item) : void{
		$this->itemInHand = $item->isNull() ? null : clone $item;
		$this->broadcastHandItems();
	}

	public function getOffhandItem() : Item{
		return $this->offhandItem ?? VanillaItems::AIR();
	}

	public function setOffhandItem(Item $item) : void{
		$this->offhandItem = $item->isNull() ? null : clone $item;
		$this->broadcastHandItems();
	}

	protected function sendSpawnPacket(Player $player) : void{
		parent::sendSpawnPacket($player);
		$this->sendHandItemsTo($player);
	}

	private function broadcastHandItems() : void{
		foreach($this->getViewers() as $viewer){
			$this->sendHandItemsTo($viewer);
		}
	}

	private function sendHandItemsTo(Player $player) : void{
		$session = $player->getNetworkSession();
		$converter = $session->getTypeConverter();

		$session->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($converter->coreItemStackToNet($this->getItemInHand())),
			0,
			0,
			ContainerIds::INVENTORY
		));

		$session->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($converter->coreItemStackToNet($this->getOffhandItem())),
			0,
			0,
			ContainerIds::OFFHAND
		));
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if($player->isSpectator()){
			return false;
		}

		$playerItem = $player->getInventory()->getItemInHand();

		if($playerItem->isNull() && $player->isSneaking()){
			$this->setPoseIndex(($this->poseIndex + 1) % self::POSE_COUNT);
			return true;
		}

		$targetSlot = $this->determineEquipmentSlot($playerItem, $clickPos);
		if($targetSlot === null){
			return false;
		}

		$soundItem = !$playerItem->isNull() ? $playerItem : $this->getEquipment($targetSlot);
		$this->swapEquipment($player, $playerItem, $targetSlot);
		$this->playEquipSound($soundItem);
		return true;
	}

	private function determineEquipmentSlot(Item $item, Vector3 $clickPos) : ?int{
		if($item instanceof Armor){
			return $item->getArmorSlot();
		}

		if(!$item->isNull()){
			return self::SLOT_MAINHAND;
		}

		$clickOffset = $clickPos->y - $this->location->y;

		if($clickOffset >= 1.6 && !$this->armorInventory->getHelmet()->isNull()){
			return ArmorInventory::SLOT_HEAD;
		}
		if($clickOffset >= 0.9 && $clickOffset < 1.6 && !$this->armorInventory->getChestplate()->isNull()){
			return ArmorInventory::SLOT_CHEST;
		}
		if($clickOffset >= 0.4 && $clickOffset < 1.2 && !$this->armorInventory->getLeggings()->isNull()){
			return ArmorInventory::SLOT_LEGS;
		}
		if($clickOffset >= 0.1 && $clickOffset < 0.55 && !$this->armorInventory->getBoots()->isNull()){
			return ArmorInventory::SLOT_FEET;
		}
		if($clickOffset < 0.3 && !$this->getOffhandItem()->isNull()){
			return self::SLOT_OFFHAND;
		}
		if(!$this->getItemInHand()->isNull()){
			return self::SLOT_MAINHAND;
		}

		return null;
	}

	private function getEquipment(int $slot) : Item{
		return match($slot){
			self::SLOT_MAINHAND => $this->getItemInHand(),
			self::SLOT_OFFHAND => $this->getOffhandItem(),
			default => $this->armorInventory->getItem($slot)
		};
	}

	private function setEquipment(int $slot, Item $item) : void{
		match($slot){
			self::SLOT_MAINHAND => $this->setItemInHand($item),
			self::SLOT_OFFHAND => $this->setOffhandItem($item),
			default => $this->armorInventory->setItem($slot, $item)
		};
	}

	private function swapEquipment(Player $player, Item $playerItem, int $slot) : void{
		$currentItem = $this->getEquipment($slot);
		$toEquip = $playerItem->isNull() ? VanillaItems::AIR() : (clone $playerItem)->setCount(1);

		$this->setEquipment($slot, $toEquip);

		if(!$playerItem->isNull() && $player->hasFiniteResources()){
			$playerItem->pop();
			$player->getInventory()->setItemInHand($playerItem);
		}

		$this->giveItemToPlayer($player, $currentItem);
	}

	private function giveItemToPlayer(Player $player, Item $item) : void{
		if($item->isNull()){
			return;
		}

		$inventory = $player->getInventory();
		if($inventory->getItemInHand()->isNull()){
			$inventory->setItemInHand($item);
			return;
		}

		foreach($inventory->addItem($item) as $leftover){
			$player->dropItem($leftover);
		}
	}

	private function playEquipSound(Item $item) : void{
		$sound = null;
		if($item instanceof Armor){
			$sound = $item->getMaterial()->getEquipSound();
		}

		$this->getWorld()->addSound($this->location, $sound ?? new ArmorStandSound(ArmorStandSoundType::PLACE));
	}

	public function applyDamageModifiers(EntityDamageEvent $source) : void{
		if($this->lastDamageCause !== null && $this->attackTime > 0){
			if($this->lastDamageCause->getBaseDamage() >= $source->getBaseDamage()){
				$source->cancel();
			}
			$source->setModifier(-$this->lastDamageCause->getBaseDamage(), EntityDamageEvent::MODIFIER_PREVIOUS_DAMAGE_COOLDOWN);
		}
	}

	protected function applyPostDamageEffects(EntityDamageEvent $source) : void{
	}

	public function attack(EntityDamageEvent $source) : void{
		$cause = $source->getCause();
		if($cause === EntityDamageEvent::CAUSE_SUFFOCATION || $cause === EntityDamageEvent::CAUSE_CONTACT){
			$source->cancel();
			return;
		}

		parent::attack($source);

		if($source->isCancelled()){
			return;
		}

		if($source instanceof EntityDamageByChildEntityEvent && $source->getChild() instanceof Arrow){
			$this->kill();
			return;
		}

		if($this->isAlive()){
			$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::VIBRATING, true);
			$this->vibrateTimer = self::VIBRATE_TICKS;
			$this->scheduleUpdate();
		}
	}

	protected function onHitGround() : ?float{
		$this->getWorld()->addSound($this->location, new ArmorStandSound(ArmorStandSoundType::FALL));
		return parent::onHitGround();
	}

	protected function doHitAnimation() : void{
		$this->getWorld()->addSound($this->location, new ArmorStandSound(ArmorStandSoundType::HIT));
	}

	protected function startDeathAnimation() : void{
		$this->getWorld()->addSound($this->location, new ArmorStandSound(ArmorStandSoundType::BREAK));
		$this->getWorld()->addParticle($this->getPosition(), new ArmorStandDestroyParticle());
	}

	protected function onDeathUpdate(int $tickDiff) : bool{
		return true;
	}

	public function knockBack(float $x, float $z, float $force = self::DEFAULT_KNOCKBACK_FORCE, ?float $verticalLimit = self::DEFAULT_KNOCKBACK_VERTICAL_LIMIT) : void{
	}

	/**
	 * @return Item[]
	 */
	public function getDrops() : array{
		$drops = [];

		foreach($this->armorInventory->getContents() as $item){
			if(!$item->isNull()){
				$drops[] = $item;
			}
		}

		$mainhand = $this->getItemInHand();
		if(!$mainhand->isNull()){
			$drops[] = $mainhand;
		}

		$offhand = $this->getOffhandItem();
		if(!$offhand->isNull()){
			$drops[] = $offhand;
		}

		$drops[] = VanillaItems::ARMOR_STAND();

		return $drops;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();

		if($this->itemInHand !== null && !$this->itemInHand->isNull()){
			$nbt->setTag(self::TAG_MAINHAND, $this->itemInHand->nbtSerialize());
		}

		if($this->offhandItem !== null && !$this->offhandItem->isNull()){
			$nbt->setTag(self::TAG_OFFHAND, $this->offhandItem->nbtSerialize());
		}

		$armorTag = new ListTag();
		for($i = ArmorInventory::SLOT_HEAD; $i <= ArmorInventory::SLOT_FEET; ++$i){
			$item = $this->armorInventory->getItem($i);
			if(!$item->isNull()){
				$armorTag->push($item->nbtSerialize($i));
			}
		}
		if($armorTag->count() > 0){
			$nbt->setTag(self::TAG_ARMOR, $armorTag);
		}

		$nbt->setInt(self::TAG_POSE_INDEX, $this->poseIndex);

		return $nbt;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->vibrateTimer > 0){
			$this->vibrateTimer -= $tickDiff;
			if($this->vibrateTimer <= 0){
				$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::VIBRATING, false);
				$this->vibrateTimer = 0;
			}
			$hasUpdate = true;
		}

		return $hasUpdate;
	}
}
