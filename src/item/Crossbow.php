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

namespace pocketmine\item;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\player\Player;
use pocketmine\world\sound\CrossbowLoadEndSound;
use pocketmine\world\sound\CrossbowShootSound;
use function intdiv;

class Crossbow extends Tool implements Releasable{

	private const TAG_CHARGED = "Charged";
	private const TAG_CHARGED_ITEM = "chargedItem";
	private const LOAD_DURATION = 25;

	public function getMaxDurability() : int{
		return 326;
	}

	public function isCharged() : bool{
		return $this->getNamedTag()->getByte(self::TAG_CHARGED, 0) === 1;
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		if(!$this->isCharged()){
			return ItemUseResult::NONE;
		}

		return $this->launchLoadedProjectile($player, $returnedItems);
	}

	public function onReleaseUsing(Player $player, array &$returnedItems) : ItemUseResult{
		return ItemUseResult::NONE;
	}

	public function continueUsing(Player $player) : bool{
		if($this->isCharged()){
			return false;
		}

		if($player->getItemUseDuration() < $this->getLoadDuration()){
			return true;
		}

		$arrow = $this->takeArrowFromPlayer($player);
		if($arrow === null){
			return false;
		}

		$loaded = clone $player->getInventory()->getItemInHand();
		if(!$loaded instanceof Crossbow){
			return false;
		}

		$loaded->setChargedProjectile($arrow);
		$player->getInventory()->setItemInHand($loaded);
		$player->getWorld()->addSound($player->getLocation(), new CrossbowLoadEndSound());
		$player->getNetworkSession()->sendDataPacket(ActorEventPacket::create(
			$player->getId(),
			ActorEvent::CHARGED_ITEM,
			0,
			null
		));

		if($player->hasFiniteResources()){
			$loaded->applyDamage(1);
			$player->getInventory()->setItemInHand($loaded);
		}

		return false;
	}

	public function canStartUsingItem(Player $player) : bool{
		if($this->isCharged()){
			return false;
		}

		return $this->findArrowInventory($player) !== null || !$player->hasFiniteResources();
	}

	private function getLoadDuration() : int{
		return self::LOAD_DURATION;
	}

	private function setChargedProjectile(Item $projectileItem) : void{
		$tag = $this->getNamedTag();
		$tag->setByte(self::TAG_CHARGED, 1);
		$tag->setTag(self::TAG_CHARGED_ITEM, $projectileItem->nbtSerialize());
		$this->setNamedTag($tag);
	}

	private function clearChargedProjectile() : void{
		$tag = $this->getNamedTag();
		$tag->setByte(self::TAG_CHARGED, 0);
		$tag->removeTag(self::TAG_CHARGED_ITEM);
		$this->setNamedTag($tag);
	}

	private function getChargedProjectileItem() : ?Item{
		$itemTag = $this->getNamedTag()->getTag(self::TAG_CHARGED_ITEM);
		if(!$itemTag instanceof CompoundTag){
			return null;
		}

		return Item::nbtDeserialize($itemTag);
	}

	/**
	 * @return \pocketmine\inventory\Inventory|null
	 */
	private function findArrowInventory(Player $player) : ?\pocketmine\inventory\Inventory{
		$arrow = VanillaItems::ARROW();
		if($player->getOffHandInventory()->contains($arrow)){
			return $player->getOffHandInventory();
		}
		if($player->getInventory()->contains($arrow)){
			return $player->getInventory();
		}

		return null;
	}

	private function takeArrowFromPlayer(Player $player) : ?Item{
		if(!$player->hasFiniteResources()){
			return VanillaItems::ARROW();
		}

		$inventory = $this->findArrowInventory($player);
		if($inventory === null){
			return null;
		}

		$arrow = VanillaItems::ARROW();
		if(!$inventory->removeItem($arrow)){
			return null;
		}

		return $arrow;
	}

	private function launchLoadedProjectile(Player $player, array &$returnedItems) : ItemUseResult{
		$chargedItem = $this->getChargedProjectileItem();
		if($chargedItem === null || $chargedItem->isNull()){
			$this->clearChargedProjectile();
			return ItemUseResult::FAIL;
		}

		$location = $player->getLocation();
		$projectile = new ArrowEntity(Location::fromObject(
			$player->getEyePos(),
			$player->getWorld(),
			($location->yaw > 180 ? 360 : 0) - $location->yaw,
			-$location->pitch
		), $player, true);
		$projectile->setMotion($player->getDirectionVector());

		if(($punchLevel = $this->getEnchantmentLevel(VanillaEnchantments::PUNCH())) > 0){
			$projectile->setPunchKnockback($punchLevel);
		}
		if(($powerLevel = $this->getEnchantmentLevel(VanillaEnchantments::POWER())) > 0){
			$projectile->setBaseDamage($projectile->getBaseDamage() + (($powerLevel + 1) / 2));
		}
		if($this->hasEnchantment(VanillaEnchantments::FLAME())){
			$projectile->setOnFire(intdiv($projectile->getFireTicks(), 20) + 100);
		}

		$force = 3.15;
		$ev = new EntityShootBowEvent($player, $this, $projectile, $force);
		if($player->isSpectator()){
			$ev->cancel();
		}
		$ev->call();

		$projectile = $ev->getProjectile();
		if($ev->isCancelled()){
			$projectile->flagForDespawn();
			return ItemUseResult::FAIL;
		}

		if($projectile instanceof Projectile){
			$projectile->setMotion($projectile->getMotion()->multiply($ev->getForce()));
			$projectileEv = new ProjectileLaunchEvent($projectile);
			$projectileEv->call();
			if($projectileEv->isCancelled()){
				$projectile->flagForDespawn();
				return ItemUseResult::FAIL;
			}

			$projectile->spawnToAll();
			$location->getWorld()->addSound($location, new CrossbowShootSound());
		}else{
			$projectile->spawnToAll();
		}

		$handItem = clone $player->getInventory()->getItemInHand();
		if($handItem instanceof Crossbow){
			$handItem->clearChargedProjectile();
			if($player->hasFiniteResources()){
				$handItem->applyDamage(3);
			}
			$player->getInventory()->setItemInHand($handItem);
		}

		return ItemUseResult::SUCCESS;
	}
}
