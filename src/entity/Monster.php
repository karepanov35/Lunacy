<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\Water;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use function abs;
use function atan2;
use function floor;
use function min;
use function rad2deg;
use function sqrt;

abstract class Monster extends Living{
	protected int $attackCooldown = 0;
	protected int $jumpCooldown = 0;
	protected int $obstacleAvoidCooldown = 0;
	protected float $smoothMotionX = 0.0;
	protected float $smoothMotionZ = 0.0;
	protected int $postHitMovementPauseTicks = 0;

	protected function tickAiCooldowns(int $tickDiff = 1) : void{
		if($this->attackCooldown > 0){
			$this->attackCooldown = max(0, $this->attackCooldown - $tickDiff);
		}
		if($this->jumpCooldown > 0){
			$this->jumpCooldown = max(0, $this->jumpCooldown - $tickDiff);
		}
		if($this->obstacleAvoidCooldown > 0){
			$this->obstacleAvoidCooldown = max(0, $this->obstacleAvoidCooldown - $tickDiff);
		}
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if(!$source->isCancelled() && $this->isAlive()){
			// Do not instantly override knockback motion with pathing logic.
			$this->postHitMovementPauseTicks = max($this->postHitMovementPauseTicks, 6);
		}
	}

	protected function tickPostHitMovementPause(int $tickDiff = 1) : bool{
		if($this->postHitMovementPauseTicks <= 0){
			return false;
		}

		$this->postHitMovementPauseTicks = max(0, $this->postHitMovementPauseTicks - $tickDiff);
		return true;
	}

	protected function hasObstacleInFront(float $dirX, float $dirZ, float $distance = 0.8) : bool{
		$len = sqrt($dirX * $dirX + $dirZ * $dirZ);
		if($len < 0.01){
			return false;
		}
		$dirX /= $len;
		$dirZ /= $len;
		$world = $this->getWorld();
		$x = (int) floor($this->location->x + $dirX * $distance);
		$z = (int) floor($this->location->z + $dirZ * $distance);
		$yFoot = (int) floor($this->location->y);
		$yHead = (int) floor($this->location->y + 1.5);

		return $world->getBlockAt($x, $yFoot, $z)->isSolid() && $world->getBlockAt($x, $yHead, $z)->isSolid();
	}

	protected function hasUnsafeDropAhead(float $dirX, float $dirZ, float $distance = 0.9, int $maxDrop = 2) : bool{
		$len = sqrt($dirX * $dirX + $dirZ * $dirZ);
		if($len < 0.01){
			return false;
		}
		$dirX /= $len;
		$dirZ /= $len;
		$world = $this->getWorld();
		$x = (int) floor($this->location->x + $dirX * $distance);
		$z = (int) floor($this->location->z + $dirZ * $distance);
		$baseY = (int) floor($this->location->y) - 1;

		for($drop = 0; $drop <= $maxDrop; ++$drop){
			if($world->getBlockAt($x, $baseY - $drop, $z)->isSolid()){
				return false;
			}
		}

		return true;
	}

	protected function getAvoidanceDirection(float $dirX, float $dirZ, bool $avoidDrops = true, bool $avoidWater = true) : ?Vector3{
		$len = sqrt($dirX * $dirX + $dirZ * $dirZ);
		if($len < 0.01){
			return null;
		}
		$dirX /= $len;
		$dirZ /= $len;

		$leftX = -$dirZ;
		$leftZ = $dirX;
		$rightX = $dirZ;
		$rightZ = -$dirX;

		$leftSafe = !$this->hasObstacleInFront($leftX, $leftZ, 0.9)
			&& (!$avoidDrops || !$this->hasUnsafeDropAhead($leftX, $leftZ, 1.0))
			&& (!$avoidWater || !$this->hasWaterAhead($leftX, $leftZ, 1.0));
		if($leftSafe){
			return new Vector3($leftX, 0, $leftZ);
		}
		$rightSafe = !$this->hasObstacleInFront($rightX, $rightZ, 0.9)
			&& (!$avoidDrops || !$this->hasUnsafeDropAhead($rightX, $rightZ, 1.0))
			&& (!$avoidWater || !$this->hasWaterAhead($rightX, $rightZ, 1.0));
		if($rightSafe){
			return new Vector3($rightX, 0, $rightZ);
		}

		return null;
	}

	protected function moveByDirection(float $dirX, float $dirZ, float $speed, bool $smooth = true, bool $avoidDrops = true) : void{
		$target = $this->location->add($dirX * 4, 0, $dirZ * 4);
		$this->moveTowardsPoint($target, $speed, $smooth, $avoidDrops);
	}

	protected function moveTowardsPoint(Vector3 $target, float $speed = 0.24, bool $smooth = true, bool $avoidDrops = true) : void{
		$x = $target->x - $this->location->x;
		$z = $target->z - $this->location->z;
		$distance = sqrt($x * $x + $z * $z);
		if($distance < 0.1){
			if($smooth){
				$this->motion->x = $this->smoothMotionX *= 0.6;
				$this->motion->z = $this->smoothMotionZ *= 0.6;
			}
			return;
		}

		$wantX = $speed * ($x / $distance);
		$wantZ = $speed * ($z / $distance);

		$len = sqrt($wantX * $wantX + $wantZ * $wantZ);
		if($len > 0.01){
			$dirX = $wantX / $len;
			$dirZ = $wantZ / $len;
			$blocked = $this->hasObstacleInFront($dirX, $dirZ, 0.85);
			$unsafeDrop = $avoidDrops && $this->hasUnsafeDropAhead($dirX, $dirZ, 0.95);
			$waterAhead = $this->hasWaterAhead($dirX, $dirZ, 0.95);
			if(($blocked || $unsafeDrop || $waterAhead) && $this->obstacleAvoidCooldown <= 0){
				$avoid = $this->getAvoidanceDirection($dirX, $dirZ, $avoidDrops, true);
				if($avoid !== null){
					$wantX = $avoid->x * $speed;
					$wantZ = $avoid->z * $speed;
					$this->obstacleAvoidCooldown = 12;
				}else{
					$wantX = 0.0;
					$wantZ = 0.0;
				}
			}
		}

		if($smooth){
			$this->smoothMotionX = $this->smoothMotionX * 0.5 + $wantX * 0.5;
			$this->smoothMotionZ = $this->smoothMotionZ * 0.5 + $wantZ * 0.5;
			$this->motion->x = $this->smoothMotionX;
			$this->motion->z = $this->smoothMotionZ;
		}else{
			$this->motion->x = $wantX;
			$this->motion->z = $wantZ;
		}

		$targetYaw = rad2deg(atan2(-$x, $z));
		$this->location->yaw = $this->lerpAngle($this->location->yaw, $targetYaw, 0.18);
	}

	protected function lerpAngle(float $from, float $to, float $t) : float{
		$diff = $to - $from + 540.0;
		$wrapped = $diff - 360.0 * floor($diff / 360.0);
		$delta = $wrapped - 180.0;
		return $from + $delta * $t;
	}

	protected function handleVanillaJump(float $horizontalBoost = 0.45) : void{
		if($this->isUnderwater() || !$this->onGround || $this->jumpCooldown > 0){
			return;
		}
		$motionLength = sqrt($this->motion->x ** 2 + $this->motion->z ** 2);
		if($motionLength < 0.05){
			return;
		}

		$dirX = $this->motion->x / $motionLength;
		$dirZ = $this->motion->z / $motionLength;
		$checkX = $this->location->x + ($dirX * 0.5);
		$checkZ = $this->location->z + ($dirZ * 0.5);
		$world = $this->getWorld();

		$blockFoot = $world->getBlockAt((int) floor($checkX), (int) floor($this->location->y), (int) floor($checkZ));
		$blockHead = $world->getBlockAt((int) floor($checkX), (int) floor($this->location->y + 1.4), (int) floor($checkZ));
		$blockAbove = $world->getBlockAt((int) floor($this->location->x), (int) floor($this->location->y + 2), (int) floor($this->location->z));

		if(!$blockFoot->isSolid() || $blockHead->isSolid() || $blockAbove->isSolid()){
			return;
		}

		$obstacleTopY = $blockFoot->getPosition()->y + 1;
		$obstacleHeight = $obstacleTopY - $this->location->y;
		if($obstacleHeight <= 1.0){
			return;
		}

		$this->jump();
		$this->motion = new Vector3($dirX * $horizontalBoost, $this->getJumpVelocity(), $dirZ * $horizontalBoost);
		$this->jumpCooldown = 12;
	}

	protected function isTouchingWater() : bool{
		if($this->isUnderwater()){
			return true;
		}

		$bb = $this->getBoundingBox();
		$minX = (int) floor($bb->minX + 0.001);
		$maxX = (int) floor($bb->maxX - 0.001);
		$minZ = (int) floor($bb->minZ + 0.001);
		$maxZ = (int) floor($bb->maxZ - 0.001);
		$world = $this->getWorld();
		$footY = (int) floor($this->location->y);

		for($x = $minX; $x <= $maxX; ++$x){
			for($z = $minZ; $z <= $maxZ; ++$z){
				if(
					$world->getBlockAt($x, $footY - 1, $z) instanceof Water ||
					$world->getBlockAt($x, $footY, $z) instanceof Water ||
					$world->getBlockAt($x, $footY + 1, $z) instanceof Water
				){
					return true;
				}
			}
		}
		return false;
	}

	protected function hasWaterAhead(float $dirX, float $dirZ, float $distance = 0.95) : bool{
		$len = sqrt($dirX * $dirX + $dirZ * $dirZ);
		if($len < 0.01){
			return false;
		}
		$dirX /= $len;
		$dirZ /= $len;
		$perpX = -$dirZ;
		$perpZ = $dirX;
		$halfWidth = max(0.3, $this->getSize()->getWidth() * 0.45);
		$samples = [
			[0.0, 0.0],
			[$perpX * $halfWidth, $perpZ * $halfWidth],
			[-$perpX * $halfWidth, -$perpZ * $halfWidth],
		];
		$world = $this->getWorld();
		$footY = (int) floor($this->location->y);
		foreach($samples as [$offsetX, $offsetZ]){
			$sampleX = $this->location->x + ($dirX * $distance) + $offsetX;
			$sampleZ = $this->location->z + ($dirZ * $distance) + $offsetZ;
			$x = (int) floor($sampleX);
			$z = (int) floor($sampleZ);
			if(
				$world->getBlockAt($x, $footY - 1, $z) instanceof Water ||
				$world->getBlockAt($x, $footY, $z) instanceof Water ||
				$world->getBlockAt($x, $footY + 1, $z) instanceof Water
			){
				return true;
			}
		}
		return false;
	}

	protected function applyWaterImmobility(float $sinkPerTick = 0.03, float $maxSinkSpeed = -0.24) : bool{
		if(!$this->isTouchingWater()){
			return false;
		}

		$this->smoothMotionX = 0.0;
		$this->smoothMotionZ = 0.0;
		$this->motion->x = 0.0;
		$this->motion->z = 0.0;
		if(!$this->onGround){
			$this->motion->y = max($maxSinkSpeed, min($this->motion->y - $sinkPerTick, -0.03));
		}else{
			$this->motion->y = min($this->motion->y, 0.0);
		}

		return true;
	}

	protected function tickWaterLock(int $tickDiff, float $sinkPerTick, float $maxSinkSpeed) : bool{
		if(!$this->applyWaterImmobility($sinkPerTick, $maxSinkSpeed)){
			return false;
		}
		$this->tickAiCooldowns($tickDiff);
		return true;
	}
}

