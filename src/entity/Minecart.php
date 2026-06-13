<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\ActivatorRail;
use pocketmine\block\BaseRail;
use pocketmine\block\DetectorRail;
use pocketmine\block\PoweredRail;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\data\bedrock\block\BlockLegacyMetadata;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function floor;
use function max;
use function min;
use function sin;
use function cos;
use function sqrt;

/**
 * @phpstan-type RailDirection array{0: array{0: int, 1: int, 2: int}, 1: array{0: int, 1: int, 2: int}}
 */
class Minecart extends Entity implements RideableEntity{

	/** @var array<int, RailDirection> */
	private const RAIL_MATRIX = [
		[[0, 0, -1], [0, 0, 1]],
		[[-1, 0, 0], [1, 0, 0]],
		[[-1, -1, 0], [1, 0, 0]],
		[[-1, 0, 0], [1, -1, 0]],
		[[0, 0, -1], [0, -1, 1]],
		[[0, -1, -1], [0, 0, 1]],
		[[0, 0, 1], [1, 0, 0]],
		[[0, 0, 1], [-1, 0, 0]],
		[[0, 0, -1], [-1, 0, 0]],
		[[0, 0, -1], [1, 0, 0]],
	];

	private const MAX_SPEED = 0.4;
	private const DRAG_LOADED = 0.997;
	private const DRAG_EMPTY = 0.96;
	private const DERAIL_DRAG = 0.96;
	private const DERAIL_STOP_SPEED = 0.004;
	private const AIR_DRAG = 0.95;
	private const POWERED_BOOST = 0.06;
	private const SLOPE_ACCEL = 0.0078125;
	private const PLAYER_PUSH = 0.0075;
	private const BASE_OFFSET = 0.35;
	private const SEAT_HEIGHT = 0.65;
	private const RIDER_SEAT_Y = self::BASE_OFFSET + self::SEAT_HEIGHT;

	private static Vector3 $riderSeatOffset;

	private ?Player $rider = null;
	private float $riderMoveX = 0.0;
	private float $riderMoveZ = 0.0;
	private float $currentSpeed = 0.0;
	private int $hurtTicks = 0;
	private int $hurtDirection = 1;

	public static function getNetworkTypeId() : string{
		return EntityIds::MINECART;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.7, 0.98);
	}

	protected function getInitialDragMultiplier() : float{ return 0.0; }
	protected function getInitialGravity() : float{ return 0.04; }

	public function getName() : string{ return "Minecart"; }

	public function getRider() : ?Player{ return $this->rider; }
	public function isSaddled() : bool{ return true; }

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(6);
		$this->setHealth(6.0);
		$this->setNoClientPredictions(true);
		$this->syncNetworkData($this->getNetworkProperties());
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setInt(EntityMetadataProperties::HURT_TIME, $this->hurtTicks);
		$properties->setInt(EntityMetadataProperties::HURT_DIRECTION, $this->hurtDirection);
		$properties->setFloat(EntityMetadataProperties::HEALTH, $this->getHealth());
		$properties->setGenericFlag(EntityMetadataFlags::HAS_COLLISION, true);
		$properties->setGenericFlag(EntityMetadataFlags::AFFECTED_BY_GRAVITY, true);
	}

	public function onUpdate(int $currentTick) : bool{
		if($this->closed){
			return false;
		}

		$tickDiff = $currentTick - $this->lastUpdate;
		if($tickDiff <= 0){
			return true;
		}
		$this->lastUpdate = $currentTick;

		if(!$this->isAlive()){
			if($this->onDeathUpdate($tickDiff)){
				$this->flagForDespawn();
			}
			return true;
		}

		if($this->hurtTicks > 0){
			$this->hurtTicks = max(0, $this->hurtTicks - $tickDiff);
			$this->networkPropertiesDirty = true;
		}

		if($this->getHealth() < 6.0 && $this->ticksLived % 20 === 0){
			$this->setHealth(min(6.0, $this->getHealth() + 1.0));
		}

		$this->motion = $this->motion->withComponents(null, $this->motion->y - $this->gravity, null);

		$railX = (int) floor($this->location->x);
		$railY = (int) floor($this->location->y);
		$railZ = (int) floor($this->location->z);
		$rail = $this->findRailBlock($railX, $railY, $railZ);

		if($rail !== null){
			$this->processOnRail($railX, $railY, $railZ, $rail);
		}else{
			$this->processOffRail();
		}

		$this->updateRiderPosition();

		$this->updateMovement();

		$hasUpdate = $this->entityBaseTick($tickDiff);
		$this->ticksLived += $tickDiff;

		return $hasUpdate || $this->motion->lengthSquared() > 0;
	}

	private function findRailBlock(int $x, int &$y, int $z) : ?BaseRail{
		$world = $this->getWorld();
		$block = $world->getBlockAt($x, $y, $z);
		if($block instanceof BaseRail){
			return $block;
		}
		--$y;
		$block = $world->getBlockAt($x, $y, $z);
		return $block instanceof BaseRail ? $block : null;
	}

	private function processOnRail(int $dx, int $dy, int $dz, BaseRail $block) : void{
		$this->fallDistance = 0.0;

		$nextRail = $this->sampleRailPoint($this->location->x, $this->location->y, $this->location->z);
		$this->location->y = (float) $dy;

		$isPowered = false;
		$isSlowed = false;
		if($block instanceof PoweredRail && $block instanceof PoweredByRedstone){
			$isPowered = $block->isPowered();
			$isSlowed = !$isPowered;
		}

		$shape = $this->getRailShape($block);
		$this->applySlopeAcceleration($shape);

		/** @var RailDirection $facing */
		$facing = self::RAIL_MATRIX[$shape];
		$facing1 = $facing[1][0] - $facing[0][0];
		$facing2 = $facing[1][2] - $facing[0][2];
		$speedOnTurns = sqrt($facing1 * $facing1 + $facing2 * $facing2);

		$motX = $this->motion->x;
		$motZ = $this->motion->z;
		$realFacing = $motX * $facing1 + $motZ * $facing2;
		if($realFacing < 0){
			$facing1 = -$facing1;
			$facing2 = -$facing2;
		}

		$sqSpeed = sqrt($motX * $motX + $motZ * $motZ);
		if($sqSpeed > 2){
			$sqSpeed = 2;
		}

		$motX = $sqSpeed * $facing1 / $speedOnTurns;
		$motZ = $sqSpeed * $facing2 / $speedOnTurns;
		$this->motion = $this->motion->withComponents($motX, $this->motion->y, $motZ);

		if($this->rider !== null && $this->currentSpeed > 0.05){
			$yawRad = $this->rider->getLocation()->yaw * M_PI / 180.0;
			$sq = $motX * $motX + $motZ * $motZ;
			if($sq < 0.01){
				if(abs($this->riderMoveX) > 0.01 || abs($this->riderMoveZ) > 0.01){
					$fX = -sin($yawRad);
					$fZ = cos($yawRad);
					$rX = cos($yawRad);
					$rZ = sin($yawRad);
					$dirX = $fX * $this->riderMoveZ + $rX * $this->riderMoveX;
					$dirZ = $fZ * $this->riderMoveZ + $rZ * $this->riderMoveX;
					$dirLen = sqrt($dirX * $dirX + $dirZ * $dirZ);
					if($dirLen > 0.001){
						$dirX = $dirX / $dirLen * 0.1;
						$dirZ = $dirZ / $dirLen * 0.1;
					}else{
						$dirX = -sin($yawRad) * 0.1;
						$dirZ = cos($yawRad) * 0.1;
					}
				}else{
					$dirX = -sin($yawRad) * 0.1;
					$dirZ = cos($yawRad) * 0.1;
				}
				$this->motion = $this->motion->withComponents($dirX, $this->motion->y, $dirZ);
				$isSlowed = false;
				$motX = $this->motion->x;
				$motZ = $this->motion->z;
			}
		}

		if($isSlowed){
			$s = sqrt($motX * $motX + $motZ * $motZ);
			if($s < 0.03){
				$this->motion = Vector3::zero();
				$motX = 0;
				$motZ = 0;
			}else{
				$motX *= 0.5;
				$motZ *= 0.5;
				$this->motion = $this->motion->withComponents($motX, 0, $motZ);
			}
		}

		$p1x = $dx + 0.5 + $facing[0][0] * 0.5;
		$p1z = $dz + 0.5 + $facing[0][2] * 0.5;
		$p2x = $dx + 0.5 + $facing[1][0] * 0.5;
		$p2z = $dz + 0.5 + $facing[1][2] * 0.5;
		$dfx = $p2x - $p1x;
		$dfz = $p2z - $p1z;

		if($dfx == 0.0){
			$this->location->x = $dx + 0.5;
			$expectedSpeed = $this->location->z - $dz;
		}elseif($dfz == 0.0){
			$this->location->z = $dz + 0.5;
			$expectedSpeed = $this->location->x - $dx;
		}else{
			$wx = $this->location->x - $p1x;
			$wz = $this->location->z - $p1z;
			$expectedSpeed = ($wx * $dfx + $wz * $dfz) * 2;
		}

		$this->location->x = $p1x + $dfx * $expectedSpeed;
		$this->location->z = $p1z + $dfz * $expectedSpeed;
		$this->setPosition($this->location);

		$motX = min(max($this->motion->x, -self::MAX_SPEED), self::MAX_SPEED);
		$motZ = min(max($this->motion->z, -self::MAX_SPEED), self::MAX_SPEED);
		if($this->rider !== null){
			$motX *= 0.75;
			$motZ *= 0.75;
		}

		$this->move($motX, 0, $motZ);

		if($facing[0][1] !== 0 && (int) floor($this->location->x) - $dx === $facing[0][0] && (int) floor($this->location->z) - $dz === $facing[0][2]){
			$this->setPosition($this->location->withComponents(null, $this->location->y + $facing[0][1], null));
		}elseif($facing[1][1] !== 0 && (int) floor($this->location->x) - $dx === $facing[1][0] && (int) floor($this->location->z) - $dz === $facing[1][2]){
			$this->setPosition($this->location->withComponents(null, $this->location->y + $facing[1][1], null));
		}

		$drag = $this->rider !== null ? self::DRAG_LOADED : self::DRAG_EMPTY;
		$this->motion = $this->motion->withComponents($this->motion->x * $drag, 0, $this->motion->z * $drag);

		$nextSample = $this->sampleRailPoint($this->location->x, $this->location->y, $this->location->z);
		if($nextSample !== null && $nextRail !== null){
			$d14 = ($nextRail->y - $nextSample->y) * 0.05;
			$sqSp = sqrt($this->motion->x * $this->motion->x + $this->motion->z * $this->motion->z);
			if($sqSp > 0){
				$this->motion = $this->motion->withComponents(
					$this->motion->x / $sqSp * ($sqSp + $d14),
					$this->motion->y,
					$this->motion->z / $sqSp * ($sqSp + $d14)
				);
			}
			$this->setPosition($this->location->withComponents(null, $nextSample->y, null));
		}

		$flX = (int) floor($this->location->x);
		$flZ = (int) floor($this->location->z);
		if($flX !== $dx || $flZ !== $dz){
			$s = sqrt($this->motion->x * $this->motion->x + $this->motion->z * $this->motion->z);
			$this->motion = $this->motion->withComponents($s * ($flX - $dx), $this->motion->y, $s * ($flZ - $dz));
		}

		if($isPowered){
			$this->applyPoweredBoost($block, $dx, $dy, $dz);
		}

		if($block instanceof ActivatorRail && $block instanceof PoweredByRedstone && $block->isPowered()){
			$this->onActivatorRail($dx, $dy, $dz);
		}

		if($block instanceof DetectorRail && !$block->isActivated()){
			$block->setActivated(true);
			$this->getWorld()->setBlock($block->getPosition(), $block);
		}
	}

	private function applySlopeAcceleration(int $shape) : void{
		match($shape){
			BlockLegacyMetadata::RAIL_ASCENDING_NORTH => $this->motion = $this->motion->withComponents($this->motion->x - self::SLOPE_ACCEL, $this->motion->y, $this->motion->z),
			BlockLegacyMetadata::RAIL_ASCENDING_SOUTH => $this->motion = $this->motion->withComponents($this->motion->x + self::SLOPE_ACCEL, $this->motion->y, $this->motion->z),
			BlockLegacyMetadata::RAIL_ASCENDING_EAST => $this->motion = $this->motion->withComponents($this->motion->x, $this->motion->y, $this->motion->z + self::SLOPE_ACCEL),
			BlockLegacyMetadata::RAIL_ASCENDING_WEST => $this->motion = $this->motion->withComponents($this->motion->x, $this->motion->y, $this->motion->z - self::SLOPE_ACCEL),
			default => null,
		};
		if($shape >= BlockLegacyMetadata::RAIL_ASCENDING_NORTH && $shape <= BlockLegacyMetadata::RAIL_ASCENDING_WEST){
			$this->location->y += 1;
		}
	}

	private function applyPoweredBoost(BaseRail $block, int $dx, int $dy, int $dz) : void{
		$ns = sqrt($this->motion->x * $this->motion->x + $this->motion->z * $this->motion->z);
		if($ns > 0.01){
			$this->motion = $this->motion->withComponents(
				$this->motion->x / $ns * ($ns + self::POWERED_BOOST),
				$this->motion->y,
				$this->motion->z / $ns * ($ns + self::POWERED_BOOST)
			);
			return;
		}

		$shape = $this->getRailShape($block);
		$world = $this->getWorld();
		if($shape === BlockLegacyMetadata::RAIL_STRAIGHT_NORTH_SOUTH){
			if($world->getBlockAt($dx - 1, $dy, $dz)->isSolid()){
				$this->motion = $this->motion->withComponents(0.02, $this->motion->y, $this->motion->z);
			}elseif($world->getBlockAt($dx + 1, $dy, $dz)->isSolid()){
				$this->motion = $this->motion->withComponents(-0.02, $this->motion->y, $this->motion->z);
			}
		}elseif($shape === BlockLegacyMetadata::RAIL_STRAIGHT_EAST_WEST){
			if($world->getBlockAt($dx, $dy, $dz - 1)->isSolid()){
				$this->motion = $this->motion->withComponents($this->motion->x, $this->motion->y, 0.02);
			}elseif($world->getBlockAt($dx, $dy, $dz + 1)->isSolid()){
				$this->motion = $this->motion->withComponents($this->motion->x, $this->motion->y, -0.02);
			}
		}
	}

	private function processOffRail() : void{
		$motX = min(max($this->motion->x, -self::MAX_SPEED), self::MAX_SPEED);
		$motY = $this->motion->y;
		$motZ = min(max($this->motion->z, -self::MAX_SPEED), self::MAX_SPEED);

		$this->move($motX, $motY, $motZ);

		if($this->onGround){
			$speed = sqrt($motX * $motX + $motZ * $motZ);
			if($speed < self::DERAIL_STOP_SPEED){
				$motX = 0.0;
				$motZ = 0.0;
			}else{
				$motX *= self::DERAIL_DRAG;
				$motZ *= self::DERAIL_DRAG;
			}
			$this->motion = new Vector3($motX, 0.0, $motZ);
		}else{
			$this->motion = $this->motion->withComponents($motX * self::AIR_DRAG, $motY * self::AIR_DRAG, $motZ * self::AIR_DRAG);
		}
	}

	private function sampleRailPoint(float $px, float $py, float $pz) : ?Vector3{
		$cx = (int) floor($px);
		$cy = (int) floor($py);
		$cz = (int) floor($pz);
		$rail = $this->findRailBlock($cx, $cy, $cz);
		if($rail === null){
			return null;
		}

		$facing = self::RAIL_MATRIX[$this->getRailShape($rail)];
		$n1 = $cx + 0.5 + $facing[0][0] * 0.5;
		$n2 = $cy + 0.5 + $facing[0][1] * 0.5;
		$n3 = $cz + 0.5 + $facing[0][2] * 0.5;
		$n4 = $cx + 0.5 + $facing[1][0] * 0.5;
		$n5 = $cy + 0.5 + $facing[1][1] * 0.5;
		$n6 = $cz + 0.5 + $facing[1][2] * 0.5;

		$ndx = $n4 - $n1;
		$ndy = ($n5 - $n2) * 2;
		$ndz = $n6 - $n3;

		if($ndx == 0.0){
			$t = $pz - $cz;
		}elseif($ndz == 0.0){
			$t = $px - $cx;
		}else{
			$t = (($px - $n1) * $ndx + ($pz - $n3) * $ndz) * 2;
		}

		$ry = $n2 + $ndy * $t;
		if($ndy < 0){
			$ry += 1;
		}elseif($ndy > 0){
			$ry += 0.5;
		}

		return new Vector3($n1 + $ndx * $t, $ry, $n3 + $ndz * $t);
	}

	private function getRailShape(BaseRail $rail) : int{
		return $rail->getShape();
	}

	protected function onActivatorRail(int $x, int $y, int $z) : void{
	}

	public function getOffsetPosition(Vector3 $vector3) : Vector3{
		return $vector3->add(0, self::BASE_OFFSET, 0);
	}

	public function isRideable() : bool{
		return true;
	}

	public function getMountedSeatHeight() : float{
		return self::RIDER_SEAT_Y;
	}

	private static function riderSeatOffset() : Vector3{
		return self::$riderSeatOffset ??= new Vector3(0, self::RIDER_SEAT_Y, 0);
	}

	public function mountPlayer(Player $player) : void{
		if($this->rider !== null){
			if($this->rider->getId() === $player->getId()){
				$this->dismountPlayer();
			}
			return;
		}

		$this->rider = $player;
		$seatOffset = self::riderSeatOffset();

		$props = $this->getNetworkProperties();
		$props->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, $seatOffset);
		$props->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, 0);
		$props->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, true);
		$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);

		$this->sendData($this->getViewers());
		$this->broadcastRiderLink($player, EntityLink::TYPE_RIDER, true);
		$this->updateRiderPosition(true);
	}

	private function updateRiderPosition(bool $onMount = false) : void{
		if($this->rider === null){
			return;
		}
		if(!$this->rider->isOnline() || $this->rider->getWorld() !== $this->getWorld()){
			$this->dismountPlayer();
			return;
		}

		$seat = self::riderSeatOffset();
		$newPos = $this->location->add($seat->x, $seat->y, $seat->z);

		if($onMount){
			$eyePos = $newPos->add(0, 1.62, 0);
			$this->rider->teleport($newPos, $this->location->yaw, $this->rider->getLocation()->pitch);
			$this->rider->getNetworkSession()->sendDataPacket(MovePlayerPacket::create(
				$this->rider->getId(),
				$eyePos,
				$this->rider->getLocation()->pitch,
				$this->rider->getLocation()->yaw,
				$this->rider->getLocation()->yaw,
				MovePlayerPacket::MODE_NORMAL,
				$this->rider->onGround,
				$this->getId(),
				0,
				0,
				0
			));
			return;
		}

		$this->rider->location->x = $newPos->x;
		$this->rider->location->y = $newPos->y;
		$this->rider->location->z = $newPos->z;
		$this->rider->location->yaw = $this->location->yaw;
		$this->rider->recalculateBoundingBox();
	}

	public function dismountPlayer() : void{
		if($this->rider === null){
			return;
		}

		$player = $this->rider;
		$this->rider = null;

		$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);

		$props = $this->getNetworkProperties();
		$props->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, false);
		$props->setByte(EntityMetadataProperties::CONTROLLING_RIDER_SEAT_NUMBER, -1);
		$props->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, Vector3::zero());

		$this->broadcastRiderLink($player, EntityLink::TYPE_REMOVE, true);
		$player->teleport($this->getLocation()->add(1, 0.1, 0));
		$player->setMotion(new Vector3(0, -0.1, 0));
	}

	public function applyRiderInput(float $moveVecX, float $moveVecZ, float $yaw) : void{
		$this->riderMoveX = $moveVecX;
		$this->riderMoveZ = $moveVecZ;
		$this->currentSpeed = sqrt($moveVecX * $moveVecX + $moveVecZ * $moveVecZ);
		$this->location->yaw = $yaw;
	}

	private function broadcastRiderLink(Player $player, int $type, bool $immediate) : void{
		$link = new EntityLink($this->getId(), $player->getId(), $type, $immediate, true, 0.0);
		$pk = SetActorLinkPacket::create($link);
		$this->getWorld()->broadcastPacketToViewers($this->getLocation(), $pk);
		$player->getNetworkSession()->sendDataPacket($pk);
		$player->getNetworkSession()->sendDataPacket(SetActorLinkPacket::create(
			new EntityLink($this->getId(), 0, $type, $immediate, true, 0.0)
		));
	}

	protected function checkAdjacentMinecartCollisions() : void{
		foreach($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(0.2, 0, 0.2), $this) as $entity){
			if($entity !== $this && $entity instanceof self && $entity->isAlive()){
				$entity->applyEntityCollision($this);
			}
		}
	}

	public function onCollideWithPlayer(Player $player) : void{
		if(!$player->isSpectator()){
			$this->pushFromEntity($player);
		}
	}

	private function pushFromEntity(Entity $entity) : void{
		if($entity === $this->rider || ($entity instanceof Player && $entity->isSpectator())){
			return;
		}

		$mx = $entity->getLocation()->x - $this->location->x;
		$mz = $entity->getLocation()->z - $this->location->z;
		$sq = $mx * $mx + $mz * $mz;
		if($sq < 0.00001){
			return;
		}

		$len = sqrt($sq);
		$factor = min(1.0, 1.0 / $len) * self::PLAYER_PUSH;
		if($this->rider === null){
			$this->motion = $this->motion->withComponents(
				$this->motion->x - ($mx / $len) * $factor,
				$this->motion->y,
				$this->motion->z - ($mz / $len) * $factor
			);
		}
	}

	public function applyEntityCollision(Entity $entity) : void{
		if($entity === $this->rider || ($entity instanceof Player && $entity->isSpectator())){
			return;
		}

		if(!($entity instanceof self)){
			return;
		}

		$motiveX = $entity->location->x - $this->location->x;
		$motiveZ = $entity->location->z - $this->location->z;
		$sq = $motiveX * $motiveX + $motiveZ * $motiveZ;
		if($sq < 0.00001){
			return;
		}

		$len = sqrt($sq);
		$motiveX = ($motiveX / $len) * min(1.0, 1.0 / $len) * 0.1 * 0.5;
		$motiveZ = ($motiveZ / $len) * min(1.0, 1.0 / $len) * 0.1 * 0.5;

		$dx = $entity->location->x - $this->location->x;
		$dz = $entity->location->z - $this->location->z;
		$dist = sqrt($dx * $dx + $dz * $dz);
		if($dist < 0.00001){
			return;
		}

		$yawRad = $this->location->yaw * M_PI / 180.0;
		$dot = abs(($dx / $dist) * cos($yawRad) + ($dz / $dist) * sin($yawRad));
		if($dot < 0.8){
			return;
		}

		$other = $entity;
		$motX = ($other->motion->x + $this->motion->x) * 0.5;
		$motZ = ($other->motion->z + $this->motion->z) * 0.5;

		$thisIsTnt = $this instanceof TNTMinecart;
		$otherIsTnt = $other instanceof TNTMinecart;

		if($otherIsTnt && !$thisIsTnt){
			$this->motion = $this->motion->withComponents(
				$this->motion->x * 0.2 + $other->motion->x - $motiveX,
				$this->motion->y,
				$this->motion->z * 0.2 + $other->motion->z - $motiveZ
			);
			$other->motion = $other->motion->withComponents(
				$other->motion->x * 0.95,
				$other->motion->y,
				$other->motion->z * 0.95
			);
		}elseif(!$otherIsTnt && $thisIsTnt){
			$other->motion = $other->motion->withComponents(
				$other->motion->x * 0.2 + $this->motion->x + $motiveX,
				$other->motion->y,
				$other->motion->z * 0.2 + $this->motion->z + $motiveZ
			);
			$this->motion = $this->motion->withComponents(
				$this->motion->x * 0.95,
				$this->motion->y,
				$this->motion->z * 0.95
			);
		}else{
			$this->motion = $this->motion->withComponents(
				$this->motion->x * 0.2 + $motX - $motiveX,
				$this->motion->y,
				$this->motion->z * 0.2 + $motZ - $motiveZ
			);
			$other->motion = $other->motion->withComponents(
				$other->motion->x * 0.2 + $motX + $motiveX,
				$other->motion->y,
				$other->motion->z * 0.2 + $motZ + $motiveZ
			);
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		if(!$this->isRideable()){
			return false;
		}
		if($this->rider !== null){
			if($this->rider->getId() === $player->getId()){
				$this->dismountPlayer();
			}
			return true;
		}
		$this->mountPlayer($player);
		return true;
	}

	public function attack(EntityDamageEvent $source) : void{
		$source->setBaseDamage($source->getBaseDamage() * 15);
		parent::attack($source);

		if($this->isAlive()){
			$this->hurtTicks = 10;
			$this->hurtDirection = -$this->hurtDirection;
			$this->networkPropertiesDirty = true;
		}
	}

	protected function onDeath() : void{
		$this->dismountPlayer();
		$this->dropMinecartItem();
	}

	protected function dropMinecartItem() : void{
		$this->getWorld()->dropItem($this->location, VanillaItems::MINECART());
	}

	public function getDrops() : array{
		return [VanillaItems::MINECART()];
	}

	public function saveNBT() : CompoundTag{
		return parent::saveNBT();
	}

	protected function onDispose() : void{
		$this->dismountPlayer();
		parent::onDispose();
	}
}
