<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\inventory\HorseInventory;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\SmokeParticle;
use function abs;
use function atan2;
use function cos;
use function deg2rad;
use function mt_rand;
use function sin;
use function sqrt;
use function str_contains;
use function strtolower;

class Horse extends Living implements RideableEntity{

	public static function getNetworkTypeId() : string{ return EntityIds::HORSE; }

	// ── Езда ──────────────────────────────────────────────────────────────────
	private ?Player $rider     = null;
	private const SEAT_HEIGHT  = 2.3;
	private const RIDE_SPEED   = 0.52;

	// ── Приручение ────────────────────────────────────────────────────────────
	private int $temper        = 0;
	private bool $isTamed      = false;

	// ── Инвентарь (седло + броня) ─────────────────────────────────────────────
	private HorseInventory $horseInventory;

	// ── AI: блуждание ─────────────────────────────────────────────────────────
	private int $moveTimer     = 0;
	private int $idleTimer     = 0;
	private ?Vector3 $wanderTarget = null;

	// ── AI: паника ────────────────────────────────────────────────────────────
	private int $panicTicks    = 0;

	// ── AI: поедание травы ────────────────────────────────────────────────────
	private bool $isEating     = false;
	private int $eatTimer      = 0;
	private ?Vector3 $grassTarget = null;

	// ── AI: прыжок ────────────────────────────────────────────────────────────
	private bool $isJumping    = false;
	private int $jumpTimer     = 0;

	// ══════════════════════════════════════════════════════════════════════════

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.6, 1.4);
	}

	public function getName() : string{ return "Horse"; }

	public function getDrops() : array{
		return [VanillaItems::LEATHER()->setCount(mt_rand(0, 2))];
	}

	public function getXpDropAmount() : int{ return mt_rand(1, 3); }

	public function getPickedItem() : ?Item{ return VanillaItems::HORSE_SPAWN_EGG(); }

	public function getHorseInventory() : HorseInventory{ return $this->horseInventory; }

	// ── NBT ───────────────────────────────────────────────────────────────────

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(15);
		$this->setStepHeight(1.0);
		$this->horseInventory = new HorseInventory($this);

		$this->isTamed  = $nbt->getByte("IsTamed", 0) === 1;
		$this->temper   = $nbt->getInt("Temper", mt_rand(0, 30));

		// Загружаем седло из NBT
		$saddleTag = $nbt->getCompoundTag("SaddleItem");
		if($saddleTag !== null){
			$saddle = Item::nbtDeserialize($saddleTag);
			if(!$saddle->isNull()) $this->horseInventory->setItem(HorseInventory::SLOT_SADDLE, $saddle);
		}

		// Загружаем броню из NBT
		$armorTag = $nbt->getCompoundTag("ArmorItem");
		if($armorTag !== null){
			$armor = Item::nbtDeserialize($armorTag);
			if(!$armor->isNull()) $this->horseInventory->setItem(HorseInventory::SLOT_ARMOR, $armor);
		}

		$this->syncMetadata();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte("IsTamed", $this->isTamed ? 1 : 0);
		$nbt->setInt("Temper", $this->temper);

		$saddle = $this->horseInventory->getSaddle();
		if(!$saddle->isNull()) $nbt->setTag("SaddleItem", $saddle->nbtSerialize());

		$armor = $this->horseInventory->getArmor();
		if(!$armor->isNull()) $nbt->setTag("ArmorItem", $armor->nbtSerialize());

		return $nbt;
	}

	// ── Геттеры / сеттеры ────────────────────────────────────────────────────

	public function getMountedSeatHeight() : float{
		return self::SEAT_HEIGHT;
	}

	public function isSaddled() : bool{
		return !$this->horseInventory->getSaddle()->isNull();
	}

	public function isTamed() : bool{ return $this->isTamed; }

	public function getRider() : ?Player{ return $this->rider; }

	public function setTamed(bool $tamed) : void{
		$this->isTamed = $tamed;
		$this->syncMetadata();
	}

	private function isBreedingItem(Item $item) : bool{
		return $item->getTypeId() === VanillaItems::GOLDEN_APPLE()->getTypeId()
			|| $item->getTypeId() === VanillaItems::GOLDEN_CARROT()->getTypeId();
	}

	private function isHorseArmor(Item $item) : bool{
		// Проверяем по typeId (стандартные предметы)
		$id = $item->getTypeId();
		if($id === VanillaItems::LEATHER_HORSE_ARMOR()->getTypeId()
			|| $id === VanillaItems::IRON_HORSE_ARMOR()->getTypeId()
			|| $id === VanillaItems::GOLDEN_HORSE_ARMOR()->getTypeId()
			|| $id === VanillaItems::DIAMOND_HORSE_ARMOR()->getTypeId()
		) return true;
		// Запасная проверка по имени (на случай перерегистрации плагином)
		$name = strtolower($item->getName());
		return str_contains($name, 'horse armor') || str_contains($name, 'horse_armor');
	}

	// ── Синхронизация метаданных ──────────────────────────────────────────────

	private function syncMetadata() : void{
		$props = $this->getNetworkProperties();
		$props->setGenericFlag(EntityMetadataFlags::TAMED,        $this->isTamed);
		$props->setGenericFlag(EntityMetadataFlags::SADDLED,      $this->isSaddled());
		$props->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, $this->rider !== null);
		$props->setGenericFlag(EntityMetadataFlags::CAN_POWER_JUMP,  $this->rider !== null && $this->isSaddled());
		$props->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, $this->rider !== null ? 0 : -1);
		if($this->rider !== null){
			$props->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0, self::SEAT_HEIGHT, 0));
		}
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::TAMED,        $this->isTamed);
		$properties->setGenericFlag(EntityMetadataFlags::SADDLED,      $this->isSaddled());
		$properties->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, $this->rider !== null);
		$properties->setGenericFlag(EntityMetadataFlags::CAN_POWER_JUMP,  $this->rider !== null && $this->isSaddled());
		$properties->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, $this->rider !== null ? 0 : -1);
		if($this->rider !== null){
			$properties->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0, self::SEAT_HEIGHT, 0));
		}
	}

	// ── Взаимодействие с игроком ──────────────────────────────────────────────

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		// Надеть седло (если не оседлана)
		$saddle = StringToItemParser::getInstance()->parse("saddle");
		if($saddle !== null && $item->getTypeId() === $saddle->getTypeId() && !$this->isSaddled()){
			$this->horseInventory->setItem(HorseInventory::SLOT_SADDLE, $item->pop());
			$player->getInventory()->setItemInHand($item);
			$this->syncMetadata();
			return true;
		}

		// Надеть броню (если приручена и нет брони)
		if($this->isTamed && $this->isHorseArmor($item) && $this->horseInventory->getArmor()->isNull()){
			$this->horseInventory->setItem(HorseInventory::SLOT_ARMOR, $item->pop());
			$player->getInventory()->setItemInHand($item);
			return true;
		}

		// Кормление золотым яблоком/морковью
		if($this->isBreedingItem($item)){
			if(!$this->isTamed){
				$this->temper = min(100, $this->temper + 5);
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				return true;
			}
			if($this->getHealth() < $this->getMaxHealth()){
				$this->setHealth(min($this->getMaxHealth(), $this->getHealth() + 5.0));
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				return true;
			}
		}

		// Открыть инвентарь лошади (Shift + ПКМ по приручённой)
		if($this->isTamed && $player->isSneaking()){
			$player->setCurrentWindow($this->horseInventory);
			return true;
		}

		// Сесть верхом (приручена + оседлана)
		if($this->isTamed && $this->isSaddled()){
			$this->mountPlayer($player);
			return true;
		}

		// Попытка приручить (пустая рука)
		if(!$this->isTamed && $item->isNull()){
			$this->tryTame($player);
			return true;
		}

		return parent::onInteract($player, $clickPos);
	}

	private function tryTame(Player $player) : void{
		$this->temper = min(100, $this->temper + mt_rand(3, 8));
		if(mt_rand(0, 100) < $this->temper){
			$this->setTamed(true);
			$this->getWorld()->addParticle(
				$this->getLocation()->add(0, $this->getEyeHeight() + 0.5, 0),
				new HeartParticle(3)
			);
		}else{
			$this->panicTicks = 60;
			$this->getWorld()->addParticle(
				$this->getLocation()->add(0, $this->getEyeHeight() + 0.5, 0),
				new SmokeParticle(3)
			);
		}
	}

	// ── Урон / паника ─────────────────────────────────────────────────────────

	public function onDamage(EntityDamageEvent $event) : void{
		parent::onDamage($event);
		if($event->isApplicable() && $event->getCause() !== EntityDamageEvent::CAUSE_SUFFOCATION){
			$this->panicTicks   = mt_rand(80, 150);
			$this->wanderTarget = null;
			$this->grassTarget  = null;
			$this->isEating     = false;
		}
	}

	// ── Тики ──────────────────────────────────────────────────────────────────

	public function onUpdate(int $currentTick) : bool{
		$hasUpdate = parent::onUpdate($currentTick);
		$this->updateAI();
		return $hasUpdate;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->isJumping){
			$this->jumpTimer -= $tickDiff;
			if($this->jumpTimer <= 0 || $this->onGround) $this->isJumping = false;
		}

		if($this->panicTicks > 0) $this->panicTicks -= $tickDiff;

		// Синхронизация позиции всадника
		if($this->rider !== null){
			if(!$this->rider->isOnline() || $this->rider->getWorld() !== $this->getWorld()){
				$this->dismountPlayer();
			}else{
				$loc = $this->getLocation();
				$this->rider->location->x = $loc->x;
				$this->rider->location->y = $loc->y + self::SEAT_HEIGHT;
				$this->rider->location->z = $loc->z;
				$this->rider->recalculateBoundingBox();
			}
		}

		return $hasUpdate;
	}

	// ── AI ────────────────────────────────────────────────────────────────────

	private function updateAI() : void{
		if($this->rider !== null){
			$this->wanderTarget = null;
			$this->motion->x *= 0.6;
			$this->motion->z *= 0.6;
			return;
		}
		if($this->panicTicks > 0){ $this->aiPanic(); return; }
		if($this->isEating){ $this->updateEating(); return; }
		if($this->grassTarget === null && mt_rand(0, 800) === 0) $this->findGrass();
		if($this->grassTarget !== null){ $this->moveToGrass(); return; }
		$this->aiWander();
	}

	private function aiPanic() : void{
		if($this->wanderTarget === null || mt_rand(1, 10) === 1){
			$angle = mt_rand(0, 359) * M_PI / 180;
			$dist  = mt_rand(8, 16);
			$ty    = $this->findSafeY(
				(int)($this->location->x + cos($angle) * $dist),
				(int)($this->location->z + sin($angle) * $dist)
			);
			if($ty !== null)
				$this->wanderTarget = new Vector3(
					$this->location->x + cos($angle) * $dist, $ty,
					$this->location->z + sin($angle) * $dist
				);
		}
		if($this->wanderTarget !== null) $this->horseMoveTo($this->wanderTarget, 0.30);
	}

	private function aiWander() : void{
		$this->moveTimer--;
		if($this->moveTimer <= 0){
			if($this->idleTimer > 0){
				$this->idleTimer--;
				$this->wanderTarget = null;
				$this->motion->x *= 0.7;
				$this->motion->z *= 0.7;
				$this->moveTimer = 10;
				return;
			}
			if(mt_rand(0, 10) < 3){
				$this->idleTimer    = mt_rand(40, 100);
				$this->wanderTarget = null;
			}else{
				$this->selectWanderTarget();
			}
			$this->moveTimer = mt_rand(20, 50);
		}
		if($this->wanderTarget !== null){
			if($this->location->distance($this->wanderTarget) < 0.8){
				$this->wanderTarget = null;
				$this->idleTimer    = mt_rand(20, 60);
			}else{
				$this->horseMoveTo($this->wanderTarget, 0.12);
			}
		}
	}

	private function selectWanderTarget() : void{
		for($i = 0; $i < 10; $i++){
			$angle = mt_rand(0, 359) * M_PI / 180;
			$dist  = mt_rand(3, 10);
			$tx    = $this->location->x + cos($angle) * $dist;
			$tz    = $this->location->z + sin($angle) * $dist;
			$ty    = $this->findSafeY((int)$tx, (int)$tz);
			if($ty !== null){ $this->wanderTarget = new Vector3($tx, $ty, $tz); return; }
		}
		$this->wanderTarget = null;
	}

	// ── Поедание травы ────────────────────────────────────────────────────────

	private function findGrass() : void{
		$world = $this->getWorld();
		$best  = null; $bestD = PHP_FLOAT_MAX;
		for($x = -10; $x <= 10; $x++){
			for($z = -10; $z <= 10; $z++){
				if(mt_rand(0, 2) !== 0) continue;
				$cx = (int)($this->location->x + $x);
				$cz = (int)($this->location->z + $z);
				for($y = (int)$this->location->y - 2; $y <= (int)$this->location->y + 1; $y++){
					if($this->isEdibleGrass($world->getBlockAt($cx, $y, $cz))){
						$d = sqrt($x * $x + $z * $z);
						if($d < $bestD){ $bestD = $d; $best = new Vector3($cx + 0.5, $y, $cz + 0.5); }
					}
				}
			}
		}
		$this->grassTarget = $best;
	}

	private function isEdibleGrass(Block $block) : bool{
		$id = $block->getTypeId();
		return $id === BlockTypeIds::GRASS || $id === BlockTypeIds::TALL_GRASS
			|| $id === BlockTypeIds::FERN  || $id === BlockTypeIds::LARGE_FERN;
	}

	private function moveToGrass() : void{
		if($this->grassTarget === null) return;
		if(!$this->isEdibleGrass($this->getWorld()->getBlockAt(
			(int)$this->grassTarget->x, (int)$this->grassTarget->y, (int)$this->grassTarget->z
		))){ $this->grassTarget = null; return; }
		if($this->location->distance($this->grassTarget) < 1.3) $this->startEating();
		else $this->horseMoveTo($this->grassTarget, 0.14);
	}

	private function startEating() : void{
		$this->isEating    = true;
		$this->eatTimer    = mt_rand(30, 50);
		$this->grassTarget = null;
		$this->setRotation($this->location->yaw, 45);
	}

	private function updateEating() : void{
		$this->eatTimer--;
		if($this->eatTimer % 5 === 0)
			$this->setRotation($this->location->yaw, $this->eatTimer % 10 === 0 ? 40 : 50);
		if($this->eatTimer <= 0){
			$this->isEating = false;
			$this->setRotation($this->location->yaw, 0);
			$world = $this->getWorld();
			$bx = (int)$this->location->x; $by = (int)($this->location->y - 0.5); $bz = (int)$this->location->z;
			if($world->getBlockAt($bx, $by, $bz)->getTypeId() === BlockTypeIds::GRASS)
				$world->setBlockAt($bx, $by, $bz, VanillaBlocks::DIRT());
			if($this->getHealth() < $this->getMaxHealth())
				$this->setHealth(min($this->getMaxHealth(), $this->getHealth() + 2.0));
			$this->idleTimer = mt_rand(60, 120);
		}
	}

	// ── Движение ──────────────────────────────────────────────────────────────

	private function horseMoveTo(Vector3 $target, float $speed) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;
		$dist = sqrt($dx * $dx + $dz * $dz);
		if($dist < 0.05) return;
		$dx /= $dist; $dz /= $dist;
		$this->setRotation(atan2(-$dx, $dz) / M_PI * 180, $this->location->pitch);
		$nx = $this->location->x + $dx * 0.5;
		$nz = $this->location->z + $dz * 0.5;
		if($this->shouldJump($nx, $nz)) $this->tryJump();
		$this->motion->x = $dx * $speed;
		$this->motion->z = $dz * $speed;
	}

	private function shouldJump(float $nx, float $nz) : bool{
		$world = $this->getWorld(); $cy = (int)$this->location->y;
		$bx = (int)round($nx); $bz = (int)round($nz);
		return $world->getBlockAt($bx, $cy, $bz)->isSolid()
			&& !$world->getBlockAt($bx, $cy + 1, $bz)->isSolid();
	}

	private function tryJump() : void{
		if($this->onGround && !$this->isJumping){
			$this->isJumping = true; $this->jumpTimer = 12; $this->motion->y = 0.6;
		}
	}

	private function findSafeY(int $x, int $z) : ?float{
		$world = $this->getWorld(); $cy = (int)$this->location->y;
		for($y = $cy + 2; $y >= $cy - 3; $y--){
			if($world->getBlockAt($x, $y, $z)->isSolid()
				&& !$world->getBlockAt($x, $y + 1, $z)->isSolid()
				&& !$world->getBlockAt($x, $y + 2, $z)->isSolid()
			) return (float)($y + 1);
		}
		return null;
	}

	// ══════════════════════════════════════════════════════════════════════════
	// ЕЗДА
	// ══════════════════════════════════════════════════════════════════════════

	public function mountPlayer(Player $player) : void{
		if($this->rider !== null){
			if($this->rider->getId() === $player->getId()) $this->dismountPlayer();
			return;
		}
		$this->rider        = $player;
		$this->wanderTarget = null;
		$this->motion->x    = 0.0;
		$this->motion->z    = 0.0;

		$player->setHasGravity(false);
		if($player->isSurvival()) $player->setAllowFlight(true);
		$player->teleport($this->getLocation()->add(0, self::SEAT_HEIGHT, 0));

		$props = $this->getNetworkProperties();
		$props->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0, self::SEAT_HEIGHT, 0));
		$props->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, true);
		$props->setGenericFlag(EntityMetadataFlags::CAN_POWER_JUMP, true);
		$props->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, 0);
		$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);

		$this->broadcastLink($player, EntityLink::TYPE_PASSENGER, true);
	}

	public function dismountPlayer() : void{
		if($this->rider === null) return;
		$player      = $this->rider;
		$this->rider = null;

		$player->setHasGravity(true);
		$player->setFlying(false);
		if($player->isSurvival()) $player->setAllowFlight(false);

		$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);
		$props = $this->getNetworkProperties();
		$props->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, false);
		$props->setGenericFlag(EntityMetadataFlags::CAN_POWER_JUMP, false);
		$props->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, -1);

		$this->broadcastLink($player, EntityLink::TYPE_REMOVE, true);
		$player->teleport($this->getLocation()->add(1, 0.1, 0));
		$player->setMotion(new Vector3(0, -0.2, 0));
	}

	/**
	 * Управление лошадью от всадника — вызывается из InGamePacketHandler.
	 */
	public function applyRiderInput(float $moveVecX, float $moveVecZ, float $yaw) : void{
		if($this->rider === null) return;
		if(abs($moveVecX) < 0.05 && abs($moveVecZ) < 0.05){
			$this->motion->x *= 0.6; $this->motion->z *= 0.6; return;
		}
		$fX = -sin(deg2rad($yaw)); $fZ = cos(deg2rad($yaw));
		$rX =  cos(deg2rad($yaw)); $rZ = sin(deg2rad($yaw));
		$mX = ($fX * $moveVecZ + $rX * $moveVecX) * self::RIDE_SPEED;
		$mZ = ($fZ * $moveVecZ + $rZ * $moveVecX) * self::RIDE_SPEED;
		$this->setRotation($yaw, 0);
		$newPos = $this->getLocation()->add($mX, 0, $mZ);
		$block  = $this->getWorld()->getBlock($newPos);
		$above  = $this->getWorld()->getBlock($newPos->add(0, 1, 0));
		if(!$block->isSolid()) $this->teleport($newPos);
		elseif(!$above->isSolid()) $this->teleport($newPos->add(0, 1, 0));
	}

	private function broadcastLink(Player $player, int $type, bool $immediate) : void{
		$link = new EntityLink($this->getId(), $player->getId(), $type, $immediate, true, 0.0);
		$pk   = SetActorLinkPacket::create($link);
		$this->getWorld()->broadcastPacketToViewers($this->getLocation(), $pk);
		$player->getNetworkSession()->sendDataPacket($pk);
		// Локальный пакет (self-link)
		$player->getNetworkSession()->sendDataPacket(
			SetActorLinkPacket::create(new EntityLink($this->getId(), 0, $type, $immediate, true, 0.0))
		);
	}

	protected function onDispose() : void{
		$this->dismountPlayer();
		parent::onDispose();
	}
}
