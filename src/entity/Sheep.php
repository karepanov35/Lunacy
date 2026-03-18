<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function mt_rand;
use function sqrt;
use function atan2;
use function cos;
use function sin;
use function abs;

class Sheep extends Living implements Ageable{
	private const TAG_COLOR = "Color";
	private const TAG_SHEARED = "Sheared";

	public static function getNetworkTypeId() : string{ return EntityIds::SHEEP; }

	private bool $baby = false;
	private int $color = 0;
	private bool $sheared = false;
	
	private int $moveTimer = 0;
	private ?Vector3 $moveTarget = null;
	private int $idleTimer = 0;
	private int $eatTimer = 0;
	private int $eatCooldown = 0;
	private ?Vector3 $fleeTarget = null;
	private int $fleeTimer = 0;
	private ?Vector3 $lastPosition = null;
	private int $stuckTimer = 0;
	
	private bool $isJumping = false;
	private int $jumpTimer = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.3, 0.9);
	}

	public function getName() : string{
		return "Sheep";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->color = $nbt->getByte(self::TAG_COLOR, mt_rand(0, 15));
		$this->sheared = $nbt->getByte(self::TAG_SHEARED, 0) === 1;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_COLOR, $this->color);
		$nbt->setByte(self::TAG_SHEARED, $this->sheared ? 1 : 0);
		return $nbt;
	}

	public function getColor() : int{
		return $this->color;
	}

	public function setColor(int $color) : void{
		$this->color = $color;
		$this->networkPropertiesDirty = true;
	}

	public function isSheared() : bool{
		return $this->sheared;
	}

	public function setSheared(bool $sheared) : void{
		$this->sheared = $sheared;
		$this->networkPropertiesDirty = true;
	}

	public function getDrops() : array{
		if($this->isSheared()){
			return [];
		}
		return [
			VanillaBlocks::WOOL()->asItem()->setCount(1)
		];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::SHEEP_SPAWN_EGG();
	}

	public function isBaby() : bool{
		return $this->baby;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);
		$properties->setByte(EntityMetadataProperties::COLOR, $this->color);
		$properties->setGenericFlag(EntityMetadataFlags::SHEARED, $this->sheared);
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		
		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Player){
				$this->startFleeing($damager->getLocation());
			}
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if($item->getTypeId() === VanillaItems::SHEARS()->getTypeId() && !$this->isSheared() && !$this->isBaby()){
			$this->shear($player);
			return true;
		}
		return false;
	}

	private function shear(Player $player) : void{
		$this->setSheared(true);
		
		$woolCount = mt_rand(1, 3);
		$wool = VanillaBlocks::WOOL()->setColor($this->getDyeColorFromId($this->color))->asItem()->setCount($woolCount);
		
		$this->getWorld()->dropItem($this->location, $wool);
		
		$item = $player->getInventory()->getItemInHand();
		$item->applyDamage(1);
		$player->getInventory()->setItemInHand($item);
	}

	private function getDyeColorFromId(int $id) : \pocketmine\block\utils\DyeColor{
		return match($id){
			0 => \pocketmine\block\utils\DyeColor::WHITE,
			1 => \pocketmine\block\utils\DyeColor::ORANGE,
			2 => \pocketmine\block\utils\DyeColor::MAGENTA,
			3 => \pocketmine\block\utils\DyeColor::LIGHT_BLUE,
			4 => \pocketmine\block\utils\DyeColor::YELLOW,
			5 => \pocketmine\block\utils\DyeColor::LIME,
			6 => \pocketmine\block\utils\DyeColor::PINK,
			7 => \pocketmine\block\utils\DyeColor::GRAY,
			8 => \pocketmine\block\utils\DyeColor::LIGHT_GRAY,
			9 => \pocketmine\block\utils\DyeColor::CYAN,
			10 => \pocketmine\block\utils\DyeColor::PURPLE,
			11 => \pocketmine\block\utils\DyeColor::BLUE,
			12 => \pocketmine\block\utils\DyeColor::BROWN,
			13 => \pocketmine\block\utils\DyeColor::GREEN,
			14 => \pocketmine\block\utils\DyeColor::RED,
			15 => \pocketmine\block\utils\DyeColor::BLACK,
			default => \pocketmine\block\utils\DyeColor::WHITE,
		};
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		
		if($this->isJumping){
			$this->jumpTimer -= $tickDiff;
			if($this->jumpTimer <= 0 || $this->onGround){
				$this->isJumping = false;
			}
		}
		
		if($this->eatTimer > 0){
			$this->eatTimer -= $tickDiff;
			if($this->eatTimer <= 0){
				$this->finishEating();
			}
		}
		
		if($this->eatCooldown > 0){
			$this->eatCooldown -= $tickDiff;
		}
		
		if($this->fleeTimer > 0){
			$this->fleeTimer -= $tickDiff;
			if($this->fleeTimer <= 0){
				$this->fleeTarget = null;
			}
		}
		
		return $hasUpdate;
	}

	public function onUpdate(int $currentTick) : bool{
		$hasUpdate = parent::onUpdate($currentTick);
		
		$this->checkIfStuck();
		
		if($this->fleeTimer > 0 && $this->fleeTarget !== null){
			$this->updateFleeing();
		}else{
			$this->updateWandering();
		}
		
		$this->tryEatGrass();
		return $hasUpdate;
	}

	private function checkIfStuck() : void{
		if($this->lastPosition === null){
			$this->lastPosition = $this->location->asVector3();
			return;
		}
		
		$distance = $this->location->distance($this->lastPosition);
		
		if($distance < 0.1 && ($this->moveTarget !== null || $this->fleeTarget !== null)){
			$this->stuckTimer++;
			
			if($this->stuckTimer > 40){
				$this->moveTarget = null;
				$this->fleeTarget = null;
				$this->stuckTimer = 0;
				$this->idleTimer = 20;
			}
		}else{
			$this->stuckTimer = 0;
		}
		
		$this->lastPosition = $this->location->asVector3();
	}

	private function startFleeing(Vector3 $dangerPos) : void{
		$dx = $this->location->x - $dangerPos->x;
		$dz = $this->location->z - $dangerPos->z;
		$distance = sqrt($dx * $dx + $dz * $dz);
		
		if($distance < 0.1){
			$dx = (mt_rand(0, 1) * 2 - 1);
			$dz = (mt_rand(0, 1) * 2 - 1);
			$distance = sqrt($dx * $dx + $dz * $dz);
		}
		
		$dx /= $distance;
		$dz /= $distance;
		
		$fleeDistance = 8;
		$targetX = $this->location->x + $dx * $fleeDistance;
		$targetZ = $this->location->z + $dz * $fleeDistance;
		
		$targetY = $this->findSafeY((int)$targetX, (int)$targetZ);
		if($targetY === null){
			$targetY = $this->location->y;
		}
		
		$this->fleeTarget = new Vector3($targetX, $targetY, $targetZ);
		$this->fleeTimer = 100;
		$this->moveTarget = null;
	}

	private function updateFleeing() : void{
		if($this->fleeTarget === null) return;
		
		$distance = $this->location->distance($this->fleeTarget);
		
		if($distance < 1.0){
			$this->fleeTarget = null;
			$this->fleeTimer = 0;
		}else{
			$this->moveTowards($this->fleeTarget, 0.18);
		}
	}

	private function updateWandering() : void{
		if($this->eatTimer > 0 || $this->fleeTimer > 0) return;
		
		$this->moveTimer--;
		
		if($this->moveTimer <= 0){
			if($this->idleTimer > 0){
				$this->idleTimer--;
				$this->moveTarget = null;
				return;
			}
			
			if(mt_rand(0, 10) < 5){
				$this->idleTimer = mt_rand(60, 120);
				$this->moveTarget = null;
			}else{
				$this->selectNewWanderTarget();
			}
			
			$this->moveTimer = mt_rand(30, 60);
		}
		
		if($this->moveTarget !== null){
			$distance = $this->location->distance($this->moveTarget);
			
			if($distance < 0.8){
				$this->moveTarget = null;
				$this->idleTimer = mt_rand(40, 80);
			}else{
				$this->moveTowards($this->moveTarget, 0.12);
			}
		}
	}

	private function selectNewWanderTarget() : void{
		for($i = 0; $i < 10; $i++){
			$angle = mt_rand(0, 360) * M_PI / 180;
			$distance = mt_rand(3, 8);
			
			$targetX = $this->location->x + cos($angle) * $distance;
			$targetZ = $this->location->z + sin($angle) * $distance;
			
			$targetY = $this->findSafeY((int)$targetX, (int)$targetZ);
			
			if($targetY !== null){
				$this->moveTarget = new Vector3($targetX, $targetY, $targetZ);
				return;
			}
		}
		
		$this->moveTarget = null;
	}

	private function findSafeY(int $x, int $z) : ?float{
		$world = $this->getWorld();
		$currentY = (int)$this->location->y;
		
		for($y = $currentY + 2; $y >= $currentY - 3; $y--){
			$block = $world->getBlockAt($x, $y, $z);
			$blockAbove = $world->getBlockAt($x, $y + 1, $z);
			$blockAbove2 = $world->getBlockAt($x, $y + 2, $z);
			
			if($block->isSolid() && !$blockAbove->isSolid() && !$blockAbove2->isSolid()){
				return (float)($y + 1);
			}
		}
		
		return null;
	}

	private function moveTowards(Vector3 $target, float $speed) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;
		$distance = sqrt($dx * $dx + $dz * $dz);
		
		if($distance < 0.05) return;
		
		$dx /= $distance;
		$dz /= $distance;
		
		$nextX = $this->location->x + $dx * 0.8;
		$nextZ = $this->location->z + $dz * 0.8;
		
		if($this->shouldJump($nextX, $nextZ)){
			$this->tryJump();
		}
		
		$targetYaw = atan2($dz, $dx) * 180 / M_PI - 90;
		$this->smoothRotate($targetYaw);
		
		$motionX = $dx * $speed;
		$motionZ = $dz * $speed;
		
		if($this->canMoveTo($this->location->x + $motionX, $this->location->z + $motionZ)){
			$this->motion = new Vector3($motionX, $this->motion->y, $motionZ);
		}else{
			$this->tryAvoidObstacle($dx, $dz, $speed);
		}
	}

	private function shouldJump(float $nextX, float $nextZ) : bool{
		$world = $this->getWorld();
		$currentY = (int)$this->location->y;
		$checkX = (int)round($nextX);
		$checkZ = (int)round($nextZ);
		
		$blockAtFeet = $world->getBlockAt($checkX, $currentY, $checkZ);
		$blockAboveFeet = $world->getBlockAt($checkX, $currentY + 1, $checkZ);
		
		if($blockAtFeet->isSolid() && !$blockAboveFeet->isSolid()) return true;
		
		$blockAboveGround = $world->getBlockAt($checkX, $currentY + 1, $checkZ);
		if($blockAboveGround->isSolid()){
			$blockAtHead = $world->getBlockAt($checkX, $currentY + 2, $checkZ);
			if(!$blockAtHead->isSolid()) return true;
		}
		
		return false;
	}

	private function tryJump() : void{
		if($this->onGround && !$this->isJumping){
			$this->isJumping = true;
			$this->jumpTimer = 10;
			$this->motion = new Vector3($this->motion->x, 0.5, $this->motion->z);
		}
	}

	private function tryAvoidObstacle(float $dx, float $dz, float $speed) : void{
		$angles = [M_PI / 4, -M_PI / 4, M_PI / 2, -M_PI / 2, M_PI * 3 / 4, -M_PI * 3 / 4];
		
		foreach($angles as $angle){
			$newDx = $dx * cos($angle) - $dz * sin($angle);
			$newDz = $dx * sin($angle) + $dz * cos($angle);
			
			if($this->canMoveTo($this->location->x + $newDx * $speed, $this->location->z + $newDz * $speed)){
				$this->motion = new Vector3($newDx * $speed, $this->motion->y, $newDz * $speed);
				$this->smoothRotate(atan2($newDz, $newDx) * 180 / M_PI - 90);
				return;
			}
		}
		
		$this->motion = new Vector3(0, $this->motion->y, 0);
		$this->moveTarget = null;
	}

	private function canMoveTo(float $x, float $z) : bool{
		$world = $this->getWorld();
		$currentY = (int)$this->location->y;
		
		for($y = $currentY; $y <= $currentY + 1; $y++){
			if($world->getBlockAt((int)$x, $y, (int)$z)->isSolid()) return false;
		}
		
		return true;
	}

	private function smoothRotate(float $targetYaw) : void{
		$currentYaw = $this->location->yaw;
		$diff = $targetYaw - $currentYaw;
		
		while($diff > 180) $diff -= 360;
		while($diff < -180) $diff += 360;
		
		if(abs($diff) < 2){
			$this->setRotation($targetYaw, $this->location->pitch);
			return;
		}
		
		$maxTurn = 15;
		if(abs($diff) > $maxTurn) $diff = ($diff > 0) ? $maxTurn : -$maxTurn;
		
		$this->setRotation($currentYaw + $diff, $this->location->pitch);
	}

	private function tryEatGrass() : void{
		if($this->sheared && $this->eatTimer <= 0 && $this->eatCooldown <= 0 && mt_rand(0, 2000) < 5){
			$world = $this->getWorld();
			$x = (int)$this->location->x;
			$y = (int)$this->location->y;
			$z = (int)$this->location->z;
			
			$blockBelow = $world->getBlockAt($x, $y - 1, $z);
			$blockAt = $world->getBlockAt($x, $y, $z);
			
			if($blockBelow->getTypeId() === BlockTypeIds::GRASS){
				$this->startEating();
			}elseif($blockAt->getTypeId() === BlockTypeIds::TALL_GRASS){
				$this->startEating();
			}
		}
	}

	private function startEating() : void{
		$this->eatTimer = 40;
		$this->moveTarget = null;
	}

	private function finishEating() : void{
		$world = $this->getWorld();
		$x = (int)$this->location->x;
		$y = (int)$this->location->y;
		$z = (int)$this->location->z;
		
		$blockBelow = $world->getBlockAt($x, $y - 1, $z);
		$blockAt = $world->getBlockAt($x, $y, $z);
		
		if($blockBelow->getTypeId() === BlockTypeIds::GRASS){
			$world->setBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
			$this->setSheared(false);
			$this->eatCooldown = 1200;
		}elseif($blockAt->getTypeId() === BlockTypeIds::TALL_GRASS){
			$world->setBlockAt($x, $y, $z, VanillaBlocks::AIR());
			$this->setSheared(false);
			$this->eatCooldown = 1200;
		}
	}
}
