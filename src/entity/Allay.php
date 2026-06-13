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

namespace pocketmine\entity;

use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\world\particle\HeartParticle;
use function atan2;
use function cos;
use function floor;
use function mt_rand;
use function sin;
use function sqrt;

class Allay extends Living {

	public static function getNetworkTypeId() : string {
		return EntityIds::ALLAY;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo {
		return new EntitySizeInfo(0.6, 0.35);
	}

	public function getName() : string {
		return "Allay";
	}

	// ── Владелец ──────────────────────────────────────────────────────────────
	private ?string $ownerUuid = null;

	// ── Предмет-шаблон (что ищем) ─────────────────────────────────────────────
	private ?Item $searchItem = null;

	// ── Предмет, который несём прямо сейчас ───────────────────────────────────
	private ?Item $carriedItem = null;

	// ── Таймеры ───────────────────────────────────────────────────────────────
	private int $pickupCooldown  = 0;   // кулдаун после подбора
	private int $deliverCooldown = 0;   // кулдаун после доставки
	private int $panicTicks      = 0;   // тики паники (убегание)
	private int $danceTicks      = 0;   // тики танца у Note Block
	private int $wanderTimer     = 0;   // таймер смены цели блуждания

	// ── Цели движения ─────────────────────────────────────────────────────────
	private ?Vector3 $moveTarget = null;

	// ── Константы ─────────────────────────────────────────────────────────────
	private const SEARCH_RADIUS  = 32.0;
	private const DELIVER_RADIUS = 2.5;
	private const SPEED_NORMAL   = 0.36;
	private const SPEED_PANIC    = 0.50;

	// ══════════════════════════════════════════════════════════════════════════
	// Инициализация / сохранение
	// ══════════════════════════════════════════════════════════════════════════

	protected function initEntity(CompoundTag $nbt) : void {
		parent::initEntity($nbt);
		$this->setMaxHealth(20);
		$this->setHealth(20);
		$this->gravity = 0.0; // летает — гравитация отключена

		$uuid = $nbt->getString("OwnerUUID", "");
		$this->ownerUuid = $uuid !== "" ? $uuid : null;
	}

	public function saveNBT() : CompoundTag {
		$nbt = parent::saveNBT();
		if ($this->ownerUuid !== null) {
			$nbt->setString("OwnerUUID", $this->ownerUuid);
		}
		return $nbt;
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Дропы / опыт
	// ══════════════════════════════════════════════════════════════════════════

	public function getDrops() : array {
		// Если нёс предмет — роняет его
		if ($this->carriedItem !== null) {
			return [clone $this->carriedItem];
		}
		return [];
	}

	public function getXpDropAmount() : int {
		return 0;
	}

	public function getPickedItem() : ?Item {
		return VanillaItems::ALLAY_SPAWN_EGG();
	}

	// Отправляет предмет в руке конкретному игроку при спавне
	protected function sendSpawnPacket(Player $player) : void {
		parent::sendSpawnPacket($player);
		$this->sendHeldItemTo($player);
	}

	// Отправляет текущий предмет в руке одному игроку
	private function sendHeldItemTo(Player $player) : void {
		$item = $this->searchItem ?? VanillaItems::AIR();
		$player->getNetworkSession()->sendDataPacket(MobEquipmentPacket::create(
			$this->getId(),
			ItemStackWrapper::legacy($player->getNetworkSession()->getTypeConverter()->coreItemStackToNet($item)),
			0,
			0,
			ContainerIds::INVENTORY
		));
	}

	// Рассылает обновление предмета в руке всем зрителям
	private function broadcastHeldItem() : void {
		$item = $this->searchItem ?? VanillaItems::AIR();
		foreach ($this->getViewers() as $viewer) {
			$viewer->getNetworkSession()->sendDataPacket(MobEquipmentPacket::create(
				$this->getId(),
				ItemStackWrapper::legacy($viewer->getNetworkSession()->getTypeConverter()->coreItemStackToNet($item)),
				0,
				0,
				ContainerIds::INVENTORY
			));
		}
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Взаимодействие с игроком
	// ══════════════════════════════════════════════════════════════════════════

	public function onInteract(Player $player, Vector3 $clickPos) : bool {
		$item = $player->getInventory()->getItemInHand();

		// Пустая рука + есть владелец → вернуть предмет
		if ($item->isNull()) {
			if ($this->ownerUuid === $player->getUniqueId()->toString() && $this->searchItem !== null) {
				$player->getInventory()->addItem(clone $this->searchItem);
				$this->searchItem  = null;
				$this->carriedItem = null;
				$this->ownerUuid   = null;
				$this->moveTarget  = null;
				$this->broadcastHeldItem(); // показываем пустую руку
				return true;
			}
			return false;
		}

		// Предмет в руке → задать шаблон поиска
		$this->searchItem  = clone $item;
		$this->searchItem->setCount(1);
		$this->carriedItem = null;
		$this->ownerUuid   = $player->getUniqueId()->toString();
		$this->moveTarget  = null;
		$this->pickupCooldown = 0;

		$this->getWorld()->addParticle(
			$this->getLocation()->add(0, $this->getEyeHeight() + 0.3, 0),
			new HeartParticle(1)
		);
		$this->broadcastHeldItem();
		return true;
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Атака / паника
	// ══════════════════════════════════════════════════════════════════════════

	public function attack(EntityDamageEvent $source) : void {
		parent::attack($source);
		if (!$source->isCancelled()) {
			$this->panicTicks = 200;
			$this->moveTarget = null;
		}
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Главный тик
	// ══════════════════════════════════════════════════════════════════════════

	protected function entityBaseTick(int $tickDiff = 1) : bool {
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if (!$this->isAlive()) return $hasUpdate;

		// Гравитация всегда 0 (летим)
		$this->gravity = 0.0;

		// Уменьшаем таймеры
		if ($this->pickupCooldown  > 0) $this->pickupCooldown  -= $tickDiff;
		if ($this->deliverCooldown > 0) $this->deliverCooldown -= $tickDiff;
		if ($this->panicTicks      > 0) $this->panicTicks      -= $tickDiff;
		if ($this->danceTicks      > 0) $this->danceTicks      -= $tickDiff;
		if ($this->wanderTimer     > 0) $this->wanderTimer     -= $tickDiff;

		$this->updateAI();
		$this->applyMovement();

		return true;
	}

	// ══════════════════════════════════════════════════════════════════════════
	// AI — главный диспетчер
	// ══════════════════════════════════════════════════════════════════════════

	private function updateAI() : void {
		// 1. Паника — убегаем
		if ($this->panicTicks > 0) {
			$this->aiPanic();
			return;
		}

		// 2. Нет владельца / шаблона — просто летаем
		if ($this->ownerUuid === null || $this->searchItem === null) {
			$this->aiWander();
			return;
		}

		// 3. Несём предмет → доставляем владельцу
		if ($this->carriedItem !== null) {
			$this->aiDeliver();
			return;
		}

		// 4. Ищем предмет на земле
		if ($this->pickupCooldown <= 0) {
			$item = $this->findNearestItem();
			if ($item !== null) {
				$this->aiPickup($item);
				return;
			}
		}

		// 5. Нет предметов — летаем рядом с владельцем
		$this->aiFollowOwner();
	}

	// ── Паника ────────────────────────────────────────────────────────────────
	private function aiPanic() : void {
		if ($this->moveTarget === null || $this->wanderTimer <= 0) {
			$angle = mt_rand(0, 359) * (M_PI / 180);
			$dist  = mt_rand(6, 12);
			$this->moveTarget  = $this->location->add(
				cos($angle) * $dist,
				mt_rand(-2, 3),
				sin($angle) * $dist
			);
			$this->wanderTimer = 15;
		}
		$this->flyTowards($this->moveTarget, self::SPEED_PANIC);
	}

	// ── Доставка предмета владельцу ───────────────────────────────────────────
	private function aiDeliver() : void {
		$owner = $this->findOwner();
		if ($owner === null) {
			// Владелец вышел — летаем на месте
			$this->aiWander();
			return;
		}

		$dist = $this->location->distance($owner->getLocation());

		if ($dist <= self::DELIVER_RADIUS && $this->deliverCooldown <= 0) {
			// Отдаём предмет
			$owner->getInventory()->addItem(clone $this->carriedItem);
			$this->carriedItem    = null;
			$this->deliverCooldown = 40;
			$this->pickupCooldown  = 20;
			$this->moveTarget      = null;

			$this->getWorld()->addParticle(
				$this->getLocation()->add(0, $this->getEyeHeight(), 0),
				new HeartParticle(1)
			);
		} else {
			// Летим к владельцу
			$this->moveTarget = $owner->getLocation()->add(
				mt_rand(-1, 1) * 0.5,
				mt_rand(0, 2),
				mt_rand(-1, 1) * 0.5
			);
			$this->flyTowards($this->moveTarget, self::SPEED_NORMAL);
		}
	}

	// ── Подбор предмета ───────────────────────────────────────────────────────
	private function aiPickup(ItemEntity $itemEntity) : void {
		$dist = $this->location->distance($itemEntity->getLocation());

		if ($dist <= 1.2) {
			// Подбираем
			$picked = clone $itemEntity->getItem();
			$picked->setCount(1);
			$this->carriedItem = $picked;
			$itemEntity->flagForDespawn();
			$this->pickupCooldown = 20;
			$this->moveTarget     = null;
		} else {
			// Летим к предмету
			$this->moveTarget = $itemEntity->getLocation()->add(0, 0.3, 0);
			$this->flyTowards($this->moveTarget, self::SPEED_NORMAL);
		}
	}

	// ── Следование за владельцем (нет предметов) ──────────────────────────────
	private function aiFollowOwner() : void {
		$owner = $this->findOwner();

		if ($owner !== null) {
			$dist = $this->location->distance($owner->getLocation());

			if ($dist > 8) {
				// Далеко — летим к нему
				$this->moveTarget = $owner->getLocation()->add(
					mt_rand(-2, 2),
					mt_rand(1, 3),
					mt_rand(-2, 2)
				);
				$this->flyTowards($this->moveTarget, self::SPEED_NORMAL);
				return;
			}

			// Рядом — летаем вокруг
			if ($this->moveTarget === null || $this->wanderTimer <= 0) {
				$this->moveTarget = $owner->getLocation()->add(
					mt_rand(-4, 4),
					mt_rand(0, 3),
					mt_rand(-4, 4)
				);
				$this->wanderTimer = 30;
			}
			$this->flyTowards($this->moveTarget, self::SPEED_NORMAL * 0.7);
		} else {
			$this->aiWander();
		}
	}

	// ── Случайное блуждание ───────────────────────────────────────────────────
	private function aiWander() : void {
		if ($this->moveTarget === null || $this->wanderTimer <= 0) {
			$angle = mt_rand(0, 359) * (M_PI / 180);
			$dist  = mt_rand(4, 10);
			$this->moveTarget = $this->location->add(
				cos($angle) * $dist,
				mt_rand(-2, 4),
				sin($angle) * $dist
			);
			$this->wanderTimer = mt_rand(20, 50);
		}
		$this->flyTowards($this->moveTarget, self::SPEED_NORMAL * 0.6);
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Движение в воздухе
	// ══════════════════════════════════════════════════════════════════════════

	private function flyTowards(Vector3 $target, float $speed) : void {
		$dx = $target->x - $this->location->x;
		$dy = $target->y - $this->location->y;
		$dz = $target->z - $this->location->z;

		$len = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
		if ($len < 0.3) {
			// Достигли цели
			$this->motion->x = 0.0;
			$this->motion->y = 0.0;
			$this->motion->z = 0.0;
			$this->moveTarget  = null;
			$this->wanderTimer = 0;
			return;
		}

		$this->motion->x = ($dx / $len) * $speed;
		$this->motion->y = ($dy / $len) * $speed;
		$this->motion->z = ($dz / $len) * $speed;

		// Поворот в сторону движения
		$yaw   = atan2($dz, $dx) * 180 / M_PI - 90;
		$pitch = -atan2($dy, sqrt($dx * $dx + $dz * $dz)) * 180 / M_PI;
		$this->setRotation($yaw, $pitch);
	}

	private function applyMovement() : void {
		// Плавное затухание если нет цели
		if ($this->moveTarget === null) {
			$this->motion->x *= 0.8;
			$this->motion->y *= 0.8;
			$this->motion->z *= 0.8;
		}

		// Лёгкое покачивание по Y (эффект парения)
		$bobOffset = sin($this->ticksLived * 0.1) * 0.015;
		$this->motion->y += $bobOffset;
	}

	// ══════════════════════════════════════════════════════════════════════════
	// Вспомогательные методы
	// ══════════════════════════════════════════════════════════════════════════

	private function findOwner() : ?Player {
		if ($this->ownerUuid === null) return null;
		foreach ($this->getWorld()->getPlayers() as $player) {
			if ($player->getUniqueId()->toString() === $this->ownerUuid) {
				return $player;
			}
		}
		return null;
	}

	private function findNearestItem() : ?ItemEntity {
		if ($this->searchItem === null) return null;

		$nearest  = null;
		$minDistSq = self::SEARCH_RADIUS * self::SEARCH_RADIUS;

		$bb = $this->getBoundingBox()->expandedCopy(
			self::SEARCH_RADIUS,
			self::SEARCH_RADIUS,
			self::SEARCH_RADIUS
		);

		foreach ($this->getWorld()->getNearbyEntities($bb) as $entity) {
			if (!($entity instanceof ItemEntity)) continue;
			if ($entity->isClosed() || !$entity->isAlive()) continue;
			if ($entity->getItem()->getTypeId() !== $this->searchItem->getTypeId()) continue;

			$distSq = $this->location->distanceSquared($entity->getLocation());
			if ($distSq < $minDistSq) {
				$minDistSq = $distSq;
				$nearest   = $entity;
			}
		}

		return $nearest;
	}

	// Вызывается снаружи (например из EventListener) когда рядом играет Note Block
	public function startDancing(Vector3 $noteBlockPos) : void {
		$this->danceTicks = 100;
		$this->moveTarget = $noteBlockPos->add(
			mt_rand(-2, 2),
			mt_rand(1, 2),
			mt_rand(-2, 2)
		);
	}

	public function isDancing() : bool {
		return $this->danceTicks > 0;
	}
}
