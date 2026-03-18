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
use function mt_rand;
use function abs;
use function sqrt;
use function atan2;
use function cos;
use function sin;

class Horse extends Living{

	public static function getNetworkTypeId() : string{ return EntityIds::HORSE; }

	private int $moveTimer = 0;
	private ?Vector3 $moveTarget = null;
	private int $idleTimer = 0;

	private bool $isPanicking = false;
	private int $panicTimer = 0;
	private ?Vector3 $panicTarget = null;

	private bool $isEating = false;
	private int $eatTimer = 0;
	private ?Vector3 $grassTarget = null;

	private bool $isJumping = false;
	private int $jumpTimer = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.6, 1.4);
	}

	public function getName() : string{
		return "Horse";
	}

	public function getDrops() : array{
		return [
			VanillaItems::LEATHER()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::HORSE_SPAWN_EGG();
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setStepHeight(1.0);
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
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

		return $hasUpdate;
	}

	private function updateAI() : void{
		if($this->isPanicking){
			$this->updatePanic();
			return;
		}

		if($this->isEating){
			$this->updateEating();
			return;
		}

		if($this->grassTarget === null && mt_rand(0, 800) === 0){
			$this->findGrass();
		}

		if($this->grassTarget !== null){
			$this->moveToGrass();
			return;
		}

		$this->updateWandering();
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
			$this->moveTowards($this->panicTarget, 0.28, true);
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

				for($y = (int)$this->location->y - 2; $y <= (int)$this->location->y + 1; $y++){
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

		$this->moveTowards($this->grassTarget, 0.14, false);
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

		if($this->getHealth() < $this->getMaxHealth()){
			$this->setHealth(min($this->getMaxHealth(), $this->getHealth() + 2));
		}

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
				$this->moveTowards($this->moveTarget, 0.12, false);
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

	private function moveTowards(Vector3 $target, float $speed, bool $panic) : void{
		$dx = $target->x - $this->location->x;
		$dz = $target->z - $this->location->z;

		$distSq = $dx * $dx + $dz * $dz;
		if($distSq < 0.0001){
			return;
		}

		$dist = sqrt($distSq);
		$dx /= $dist;
		$dz /= $dist;

		$yaw = atan2(-$dx, $dz) / M_PI * 180;
		$this->setRotation($yaw, $this->location->pitch);

		$motion = $this->getMotion();
		$motion->x = $dx * $speed;
		$motion->z = $dz * $speed;

		if($panic){
			if($this->onGround && $this->jumpTimer <= 0){
				$motion->y = 0.42;
				$this->isJumping = true;
				$this->jumpTimer = 10;
			}
		}

		$this->setMotion($motion);
	}

	private function findSafeY(int $x, int $z) : ?int{
		$world = $this->getWorld();
		$currentY = (int)$this->location->y;

		for($y = $currentY + 2; $y >= $currentY - 2; $y--){
			$block = $world->getBlockAt($x, $y, $z);
			$blockAbove = $world->getBlockAt($x, $y + 1, $z);
			$blockAbove2 = $world->getBlockAt($x, $y + 2, $z);

			if($block->isSolid() && !$blockAbove->isSolid() && !$blockAbove2->isSolid()){
				return $y + 1;
			}
		}

		return null;
	}

	public function onDamage(EntityDamageEvent $event) : void{
		parent::onDamage($event);

		if($event->isApplicable() && $event->getCause() !== EntityDamageEvent::CAUSE_SUFFOCATION){
			$this->isPanicking = true;
			$this->panicTimer = mt_rand(60, 120);
			$this->panicTarget = null;
		}
	}
}
