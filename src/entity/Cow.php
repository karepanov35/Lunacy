<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function mt_rand;
use function abs;
use function sqrt;
use function atan2;
use function cos;
use function sin;
use function min;

class Cow extends Living implements Ageable{

	public static function getNetworkTypeId() : string{ return EntityIds::COW; }

	private int $age = 0;
	private bool $ageLocked = false;
	private const BABY_AGE = -24000;
	
	private int $moveTimer = 0;
	private ?Vector3 $moveTarget = null;
	private int $idleTimer = 0;
	
	private bool $isPanicking = false;
	private int $panicTimer = 0;
	private ?Vector3 $panicTarget = null;
	
	private bool $isEating = false;
	private int $eatTimer = 0;
	private ?Vector3 $grassTarget = null;
	
	private ?Player $temptingPlayer = null;
	private int $temptCooldown = 0;
	
	private bool $isJumping = false;
	private int $jumpTimer = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.4, 0.9);
	}

	public function getName() : string{
		return "Cow";
	}

	public function getDrops() : array{
		if($this->isBaby()) return [];
		return [
			VanillaItems::RAW_BEEF()->setCount(mt_rand(1, 3)),
			VanillaItems::LEATHER()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::COW_SPAWN_EGG();
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function getAge() : int{
		return $this->age;
	}

	public function setAge(int $age) : void{
		$this->age = $age;
	}

	public function ageUp(int $amount = 1) : void{
		if(!$this->ageLocked){
			$this->age += $amount;
			if($this->age > 0) $this->age = 0;
		}
	}

	public function setBaby(bool $baby = true) : void{
		$this->age = $baby ? self::BABY_AGE : 0;
	}

	public function setAgeLocked(bool $locked) : void{
		$this->ageLocked = $locked;
	}

	public function isAgeLocked() : bool{
		return $this->ageLocked;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt("Age", $this->age);
		$nbt->setByte("AgeLocked", $this->ageLocked ? 1 : 0);
		return $nbt;
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->age = $nbt->getInt("Age", 0);
		$this->ageLocked = $nbt->getByte("AgeLocked", 0) !== 0;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
	}

	public function onUpdate(int $currentTick) : bool{
		$hasUpdate = parent::onUpdate($currentTick);
		$this->updateAI();
		return $hasUpdate;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		
		if($this->isJumping){
			$this->jumpTimer -= $tickDiff;
			if($this->jumpTimer <= 0 || $this->onGround){
				$this->isJumping = false;
			}
		}
		
		if(!$this->ageLocked && $this->age < 0){
			$this->ageUp($tickDiff);
			if($this->age === 0){
				$this->broadcastAnimation(new \pocketmine\entity\animation\BabyAnimalFeedAnimation($this));
			}
		}
		
		return $hasUpdate;
	}

	private function updateAI() : void{
		if($this->isPanicking){
			$this->updatePanic();
			return;
		}
		
		if($this->checkTempt()) return;
		
		if($this->isEating){
			$this->updateEating();
			return;
		}
		
		if($this->grassTarget === null && $this->temptingPlayer === null && mt_rand(0, 800) === 0){
			$this->findGrass();
		}
		
		if($this->grassTarget !== null){
			$this->moveToGrass();
			return;
		}
		
		$this->updateWandering();
	}

	private function checkTempt() : bool{
		if($this->temptCooldown > 0){
			$this->temptCooldown--;
			if($this->temptCooldown <= 0) $this->temptingPlayer = null;
		}
		
		$nearestPlayer = null;
		$nearestDistance = 10;
		
		foreach($this->getWorld()->getPlayers() as $player){
			$distance = $this->location->distance($player->getLocation());
			if($distance < $nearestDistance && $this->isHoldingWheat($player)){
				$nearestDistance = $distance;
				$nearestPlayer = $player;
			}
		}
		
		if($nearestPlayer !== null){
			$this->temptingPlayer = $nearestPlayer;
			$this->temptCooldown = 5;
			$playerPos = $nearestPlayer->getLocation();
			
			if($nearestDistance > 2.5){
				$this->moveTowards($playerPos, $this->isBaby() ? 0.2 : 0.15, false);
			}else{
				$this->lookAt($playerPos);
			}
			return true;
		}
		
		$this->temptingPlayer = null;
		return false;
	}

	private function isHoldingWheat(Player $player) : bool{
		return $player->getInventory()->getItemInHand()->getTypeId() === VanillaItems::WHEAT()->getTypeId();
	}

	public function lookAt(Vector3 $target) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;
		$yaw = atan2($dz, $dx) * 180 / M_PI - 90;
		$this->smoothRotate($yaw);
	}

	private function updatePanic() : void{
		$this->panicTimer--;
		
		if($this->panicTimer <= 0){
			$this->stopPanic();
			return;
		}
		
		if($this->panicTarget === null || $this->location->distance($this->panicTarget) < 1.5){
			$this->panicTarget = $this->findPanicTarget();
		}
		
		if($this->panicTarget !== null){
			$this->moveTowards($this->panicTarget, $this->isBaby() ? 0.3 : 0.25, true);
		}
	}

	private function findPanicTarget() : ?Vector3{
		$world = $this->getWorld();
		
		for($i = 0; $i < 10; $i++){
			$angle = mt_rand(0, 360) * M_PI / 180;
			$distance = mt_rand(8, 16);
			
			$targetX = $this->location->x + cos($angle) * $distance;
			$targetZ = $this->location->z + sin($angle) * $distance;
			$targetY = $this->location->y;
			
			for($y = (int)$targetY + 3; $y >= (int)$targetY - 3; $y--){
				$block = $world->getBlockAt((int)$targetX, $y, (int)$targetZ);
				$blockAbove = $world->getBlockAt((int)$targetX, $y + 1, (int)$targetZ);
				$blockAbove2 = $world->getBlockAt((int)$targetX, $y + 2, (int)$targetZ);
				
				if($block->isSolid() && !$blockAbove->isSolid() && !$blockAbove2->isSolid()){
					return new Vector3($targetX, $y + 1, $targetZ);
				}
			}
		}
		
		return null;
	}

	private function stopPanic() : void{
		$this->isPanicking = false;
		$this->panicTimer = 0;
		$this->panicTarget = null;
		$this->moveTarget = null;
		$this->moveTimer = 0;
	}

	private function findGrass() : void{
		$world = $this->getWorld();
		$nearestGrass = null;
		$nearestDistance = PHP_FLOAT_MAX;
		
		for($x = -10; $x <= 10; $x++){
			for($z = -10; $z <= 10; $z++){
				if(mt_rand(0, 2) !== 0) continue;
				
				$checkX = (int)($this->location->x + $x);
				$checkZ = (int)($this->location->z + $z);
				
				for($y = (int)($this->location->y - 2); $y <= (int)($this->location->y + 1); $y++){
					$block = $world->getBlockAt($checkX, $y, $checkZ);
					
					if($this->isEdibleGrass($block)){
						$distance = sqrt($x * $x + $z * $z);
						if($distance < $nearestDistance){
							$nearestDistance = $distance;
							$nearestGrass = new Vector3($checkX + 0.5, $y, $checkZ + 0.5);
						}
					}
				}
			}
		}
		
		if($nearestGrass !== null) $this->grassTarget = $nearestGrass;
	}

	private function isEdibleGrass(Block $block) : bool{
		$id = $block->getTypeId();
		return $id === BlockTypeIds::GRASS || 
		       $id === BlockTypeIds::TALL_GRASS ||
		       $id === BlockTypeIds::FERN ||
		       $id === BlockTypeIds::LARGE_FERN;
	}

	private function moveToGrass() : void{
		if($this->grassTarget === null) return;
		
		$distance = $this->location->distance($this->grassTarget);
		
		if($distance < 1.3){
			$this->startEating();
			return;
		}
		
		$block = $this->getWorld()->getBlockAt(
			(int)$this->grassTarget->x,
			(int)$this->grassTarget->y,
			(int)$this->grassTarget->z
		);
		
		if(!$this->isEdibleGrass($block)){
			$this->grassTarget = null;
			return;
		}
		
		$this->moveTowards($this->grassTarget, 0.12, false);
	}

	private function startEating() : void{
		$this->isEating = true;
		$this->eatTimer = mt_rand(30, 50);
		$this->grassTarget = null;
		$this->setRotation($this->location->yaw, 45);
	}

	private function updateEating() : void{
		$this->eatTimer--;
		
		if($this->eatTimer % 5 === 0){
			$this->setRotation($this->location->yaw, $this->eatTimer % 10 === 0 ? 40 : 50);
		}
		
		if($this->eatTimer <= 0) $this->finishEating();
	}

	private function finishEating() : void{
		$this->isEating = false;
		$this->eatTimer = 0;
		$this->setRotation($this->location->yaw, 0);
		
		$world = $this->getWorld();
		$blockX = (int)$this->location->x;
		$blockY = (int)($this->location->y - 0.5);
		$blockZ = (int)$this->location->z;
		
		$blockBelow = $world->getBlockAt($blockX, $blockY, $blockZ);
		if($blockBelow->getTypeId() === BlockTypeIds::GRASS){
			$world->setBlockAt($blockX, $blockY, $blockZ, VanillaBlocks::DIRT());
		}
		
		$healAmount = $this->isBaby() ? 4 : 2;
		if($this->getHealth() < $this->getMaxHealth()){
			$this->setHealth(min($this->getMaxHealth(), $this->getHealth() + $healAmount));
		}
		
		if($this->isBaby()) $this->ageUp(600);
		
		$this->idleTimer = mt_rand(60, 120);
	}

	private function updateWandering() : void{
		$this->moveTimer--;
		
		if($this->moveTimer <= 0){
			if($this->idleTimer > 0){
				$this->idleTimer--;
				$this->moveTarget = null;
				return;
			}
			
			if(mt_rand(0, 10) < 3){
				$this->idleTimer = mt_rand(40, 100);
				$this->moveTarget = null;
			}else{
				$this->selectNewWanderTarget();
			}
			
			$this->moveTimer = mt_rand(20, 50);
		}
		
		if($this->moveTarget !== null){
			$distance = $this->location->distance($this->moveTarget);
			
			if($distance < 0.8){
				$this->moveTarget = null;
				$this->idleTimer = mt_rand(20, 60);
			}else{
				$this->moveTowards($this->moveTarget, $this->isBaby() ? 0.08 : 0.1, false);
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

	private function moveTowards(Vector3 $target, float $speed, bool $isPanic) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;
		$distance = sqrt($dx * $dx + $dz * $dz);
		
		if($distance < 0.05) return;
		
		$dx /= $distance;
		$dz /= $distance;
		
		$nextX = $this->location->x + $dx * 0.5;
		$nextZ = $this->location->z + $dz * 0.5;
		
		if($this->shouldJump($nextX, $nextZ)) $this->tryJump();
		
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
			$this->motion = new Vector3($this->motion->x, $this->isBaby() ? 0.4 : 0.5, $this->motion->z);
		}
	}

	private function tryAvoidObstacle(float $dx, float $dz, float $speed) : void{
		$angles = [M_PI / 4, -M_PI / 4, M_PI / 2, -M_PI / 2];
		
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

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if(!$source->isCancelled()){
			$this->startPanic();
		}
	}

	private function startPanic() : void{
		$this->isPanicking = true;
		$this->panicTimer = mt_rand(80, 150);
		$this->panicTarget = null;
		$this->grassTarget = null;
		$this->isEating = false;
		$this->eatTimer = 0;
		$this->temptingPlayer = null;
		$this->setRotation($this->location->yaw, 0);
	}
	
	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		
		if($item->getTypeId() === VanillaItems::BUCKET()->getTypeId() && !$this->isBaby()){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			$player->getInventory()->addItem(VanillaItems::MILK_BUCKET());
			return true;
		}
		
		if($item->getTypeId() === VanillaItems::WHEAT()->getTypeId()){
			if(!$this->isBaby() && $this->getHealth() < $this->getMaxHealth()){
				$this->setHealth(min($this->getMaxHealth(), $this->getHealth() + 2));
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				return true;
			}
			
			if($this->isBaby()){
				$this->ageUp(1200);
				$this->broadcastAnimation(new \pocketmine\entity\animation\BabyAnimalFeedAnimation($this));
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				return true;
			}
		}
		
		return false;
	}
}
