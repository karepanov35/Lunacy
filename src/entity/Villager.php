<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use function mt_rand;
use function sqrt;
use function atan2;
use function cos;
use function sin;
use function abs;

class Villager extends Living implements Ageable{
	public const PROFESSION_NONE = 0;
	public const PROFESSION_FARMER = 1;
	public const PROFESSION_FISHERMAN = 2;
	public const PROFESSION_SHEPHERD = 3;
	public const PROFESSION_FLETCHER = 4;
	public const PROFESSION_LIBRARIAN = 5;
	public const PROFESSION_CARTOGRAPHER = 6;
	public const PROFESSION_CLERIC = 7;
	public const PROFESSION_ARMORER = 8;
	public const PROFESSION_WEAPONSMITH = 9;
	public const PROFESSION_TOOLSMITH = 10;
	public const PROFESSION_BUTCHER = 11;
	public const PROFESSION_LEATHERWORKER = 12;
	public const PROFESSION_MASON = 13;

	private const TAG_PROFESSION = "Profession";

	public static function getNetworkTypeId() : string{ return EntityIds::VILLAGER_V2; }

	private bool $baby = false;
	private int $profession = self::PROFESSION_NONE;
	
	private int $moveTimer = 0;
	private ?Vector3 $moveTarget = null;
	private int $idleTimer = 0;
	
	private bool $isJumping = false;
	private int $jumpTimer = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	public function getName() : string{
		return "Villager";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$profession = $nbt->getInt(self::TAG_PROFESSION, self::PROFESSION_NONE);

		if($profession > 13 || $profession < 0){
			$profession = self::PROFESSION_NONE;
		}

		if($profession === self::PROFESSION_NONE){
			$profession = mt_rand(0, 13);
		}

		$this->setProfession($profession);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_PROFESSION, $this->getProfession());
		return $nbt;
	}

	public function setProfession(int $profession) : void{
		$this->profession = $profession;
		$this->networkPropertiesDirty = true;
	}

	private function updateNameTag() : void{
		$this->setNameTag("");
		$this->setNameTagAlwaysVisible(false);
	}

	public function getProfession() : int{
		return $this->profession;
	}

	public function isBaby() : bool{
		return $this->baby;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::VILLAGER_SPAWN_EGG();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->profession);
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

	public function onUpdate(int $currentTick) : bool{
		$hasUpdate = parent::onUpdate($currentTick);
		$this->updateWandering();
		return $hasUpdate;
	}

	private function updateWandering() : void{
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
		$checkX = (int)$nextX;
		$checkZ = (int)$nextZ;
		
		$blockInFront = $world->getBlockAt($checkX, $currentY, $checkZ);
		$blockAbove = $world->getBlockAt($checkX, $currentY + 1, $checkZ);
		
		if($blockInFront->isSolid() && !$blockAbove->isSolid()){
			return true;
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
}



