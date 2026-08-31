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

use pocketmine\entity\object\FireworkRocket as FireworkRocketEntity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\Arrow as ArrowItem;
use pocketmine\item\FireworkRocket as FireworkRocketItem;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\player\Player;
use pocketmine\world\sound\CrossbowLoadEndSound;
use pocketmine\world\sound\CrossbowShootSound;
use function cos;
use function deg2rad;
use function intdiv;
use function mt_rand;
use function sin;

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

		$ammo = $this->takeAmmoFromPlayer($player);
		if($ammo === null){
			return false;
		}

		$loaded = clone $player->getInventory()->getItemInHand();
		if(!$loaded instanceof Crossbow){
			return false;
		}

		$loaded->setChargedProjectile($ammo);
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
		$player->resetItemCooldown($loaded, 1);

		return false;
	}

	public function canStartUsingItem(Player $player) : bool{
		if($this->isCharged()){
			return false;
		}

		return $this->findAmmoItem($player) !== null || !$player->hasFiniteResources();
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
	 * @return array{0:\pocketmine\inventory\Inventory,1:int,2:Item}|null
	 */
	private function findAmmoItem(Player $player) : ?array{
		foreach([$player->getOffHandInventory(), $player->getInventory()] as $inventory){
			foreach($inventory->getContents() as $slot => $item){
				if($this->isValidAmmo($item)){
					if($item instanceof FireworkRocketItem && $inventory !== $player->getOffHandInventory()){
						continue;
					}

					return [$inventory, $slot, clone $item];
				}
			}
		}

		return null;
	}

	private function isValidAmmo(Item $item) : bool{
		return $item instanceof ArrowItem || $item instanceof FireworkRocketItem;
	}

	private function takeAmmoFromPlayer(Player $player) : ?Item{
		$ammo = $this->findAmmoItem($player);
		if($ammo !== null){
			[$inventory, $slot, $item] = $ammo;
			$item->setCount(1);
			if($player->hasFiniteResources()){
				$remaining = $inventory->getItem($slot);
				$remaining->pop();
				if($remaining->isNull()){
					$inventory->clear($slot);
				}else{
					$inventory->setItem($slot, $remaining);
				}
			}

			return $item;
		}

		if(!$player->hasFiniteResources()){
			return VanillaItems::ARROW();
		}

		return null;
	}

	private function launchLoadedProjectile(Player $player, array &$returnedItems) : ItemUseResult{
		$chargedItem = $this->getChargedProjectileItem();
		if($chargedItem === null || $chargedItem->isNull()){
			$this->clearChargedProjectile();
			return ItemUseResult::FAIL;
		}

		$shotCount = 0;

		foreach([0.0] as $yawOffset){
			if($this->launchSingleCrossbowShot($player, $chargedItem, $yawOffset)){
				++$shotCount;
			}
		}

		if($shotCount === 0){
			return ItemUseResult::FAIL;
		}

		$player->getWorld()->addSound($player->getLocation(), new CrossbowShootSound());

		$handItem = clone $player->getInventory()->getItemInHand();
		if($handItem instanceof Crossbow){
			$handItem->clearChargedProjectile();
			if($player->hasFiniteResources()){
				$handItem->applyDamage(1);
			}
			$player->getInventory()->setItemInHand($handItem);
		}

		return ItemUseResult::SUCCESS;
	}

	private function launchSingleCrossbowShot(Player $player, Item $chargedItem, float $yawOffset) : bool{
		$location = $player->getLocation();
		$yaw = $location->getYaw() + $yawOffset;
		$spawnLocation = Location::fromObject(
			$player->getEyePos(),
			$player->getWorld(),
			($yaw > 180 ? 360 : 0) - $yaw,
			-$location->getPitch()
		);

		if($chargedItem instanceof FireworkRocketItem){
			$firework = new FireworkRocketEntity(
				$spawnLocation,
				((($chargedItem->getFlightTimeMultiplier() + 1) * 10) + mt_rand(0, 12)),
				$chargedItem->getExplosions()
			);
			$firework->setOwningEntity($player);
			$firework->setMotion($this->getShotDirectionVector($location->getYaw() + $yawOffset, $location->getPitch()));
			$firework->spawnToAll();
			return true;
		}

		$projectile = new ArrowEntity($spawnLocation, $player, true);
		$projectile->setMotion($this->getShotDirectionVector($location->getYaw() + $yawOffset, $location->getPitch()));

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
			return false;
		}

		if($projectile instanceof Projectile){
			$projectile->setMotion($projectile->getMotion()->multiply($ev->getForce()));
			$projectileEv = new ProjectileLaunchEvent($projectile);
			$projectileEv->call();
			if($projectileEv->isCancelled()){
				$projectile->flagForDespawn();
				return false;
			}

			$projectile->spawnToAll();
		}else{
			$projectile->spawnToAll();
		}

		return true;
	}

	private function getShotDirectionVector(float $yaw, float $pitch) : Vector3{
		$y = -sin(deg2rad($pitch));
		$xz = cos(deg2rad($pitch));
		$x = -$xz * sin(deg2rad($yaw));
		$z = $xz * cos(deg2rad($yaw));
		return (new Vector3($x, $y, $z))->normalize();
	}
}
