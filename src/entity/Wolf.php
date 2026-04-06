<?php
declare(strict_types=1);
namespace pocketmine\entity;

use pocketmine\block\utils\DyeColor;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Dye;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\particle\HeartParticle;
use Ramsey\Uuid\Uuid;
use function atan2;
use function cos;
use function floor;
use function max;
use function min;
use function mt_rand;
use function rad2deg;
use function sin;
use function sqrt;
use function abs;
use function deg2rad;
use const M_PI;

class Wolf extends Living implements Ageable{

	public const LEASH_INTERACT_NONE = 0;
	public const LEASH_INTERACT_ATTACHED = 1;
	public const LEASH_INTERACT_DETACHED = 2;

	private const TAG_OWNER_UUID = "OwnerUUID";
	private const TAG_TAMED = "Tamed";
	private const TAG_SITTING = "Sitting";
	private const TAG_COLLAR_COLOR = "CollarColor";
	private const TAG_LEASH_UUID = "LeashUUID";

	private bool $tamed = false;
	private bool $sitting = false;
	private ?string $ownerUuid = null;
	private int $collarColor = 14;

	private ?string $leashHolderUuid = null;

	private ?Player $angryAtPlayer = null;
	private int $angryTicksRemaining = 0;

	private ?Living $combatTarget = null;
	private int $attackCooldown = 0;
	private int $jumpCooldown = 0;
	private int $idleTime = 0;
	private ?Vector3 $wanderTarget = null;
	private int $obstacleAvoidCooldown = 0;
	private float $smoothMotionX = 0.0;
	private float $smoothMotionZ = 0.0;

	private bool $lastAngryMetadata = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::WOLF;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.85, 0.6);
	}

	public function getName() : string{
		return "Wolf";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->setMaxHealth(16);
		$this->setHealth(min($this->getHealth(), 16.0));
		$this->setStepHeight(1.0);

		$this->tamed = $nbt->getByte(self::TAG_TAMED, 0) !== 0;
		$this->sitting = $nbt->getByte(self::TAG_SITTING, 0) !== 0;
		$this->collarColor = $nbt->getByte(self::TAG_COLLAR_COLOR, 14);
		$ou = $nbt->getString(self::TAG_OWNER_UUID, "");
		$this->ownerUuid = $ou !== "" ? $ou : null;
		$lu = $nbt->getString(self::TAG_LEASH_UUID, "");
		$this->leashHolderUuid = $lu !== "" ? $lu : null;
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_TAMED, $this->tamed ? 1 : 0);
		$nbt->setByte(self::TAG_SITTING, $this->sitting ? 1 : 0);
		$nbt->setByte(self::TAG_COLLAR_COLOR, $this->collarColor);
		if($this->ownerUuid !== null){
			$nbt->setString(self::TAG_OWNER_UUID, $this->ownerUuid);
		}
		if($this->leashHolderUuid !== null){
			$nbt->setString(self::TAG_LEASH_UUID, $this->leashHolderUuid);
		}

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::TAMED, $this->tamed);
		$properties->setGenericFlag(EntityMetadataFlags::SITTING, $this->sitting);
		$angry = $this->angryAtPlayer !== null || ($this->combatTarget instanceof Player && !$this->tamed);
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $angry);
		$properties->setByte(EntityMetadataProperties::COLOR, $this->tamed ? $this->collarColor : 0);

		$leadHolderEid = -1;
		if($this->leashHolderUuid !== null){
			$lh = $this->resolveLeashHolder();
			if($lh !== null && $lh->getWorld() === $this->getWorld()){
				$leadHolderEid = $lh->getId();
			}
		}
		$properties->setLong(EntityMetadataProperties::LEAD_HOLDER_EID, $leadHolderEid);
	}

	private function isOwner(Player $player) : bool{
		return $this->ownerUuid !== null && $player->getUniqueId()->toString() === $this->ownerUuid;
	}

	private function resolveOwner() : ?Player{
		if($this->ownerUuid === null){
			return null;
		}
		try{
			$uuid = Uuid::fromString($this->ownerUuid);
		}catch(\InvalidArgumentException){
			return null;
		}

		return Server::getInstance()->getPlayerByUUID($uuid);
	}

	private function resolveLeashHolder() : ?Player{
		if($this->leashHolderUuid === null){
			return null;
		}
		try{
			$uuid = Uuid::fromString($this->leashHolderUuid);
		}catch(\InvalidArgumentException){
			return null;
		}

		return Server::getInstance()->getPlayerByUUID($uuid);
	}

	public function toggleLeashWithLead(Player $player) : int{
		$uuid = $player->getUniqueId()->toString();
		if($this->leashHolderUuid !== null){
			if($this->leashHolderUuid !== $uuid){
				return self::LEASH_INTERACT_NONE;
			}
			$this->leashHolderUuid = null;
			$this->networkPropertiesDirty = true;

			return self::LEASH_INTERACT_DETACHED;
		}
		$this->leashHolderUuid = $uuid;
		$this->networkPropertiesDirty = true;

		return self::LEASH_INTERACT_ATTACHED;
	}

	private function breakLeash(bool $dropLeadItem) : void{
		if($dropLeadItem){
			$this->getWorld()->dropItem($this->location->add(0, 0.4, 0), VanillaItems::LEAD());
		}
		$this->leashHolderUuid = null;
		$this->networkPropertiesDirty = true;
	}

	private function tickLeashFollow(Player $holder) : void{
		$yaw = $holder->getLocation()->yaw;
		$rad = deg2rad($yaw);
		$backX = sin($rad);
		$backZ = -cos($rad);
		$p = $holder->getPosition();
		$want = new Vector3($p->x + $backX * 1.9, $p->y, $p->z + $backZ * 1.9);

		$dx = $want->x - $this->location->x;
		$dz = $want->z - $this->location->z;
		$dist = sqrt($dx * $dx + $dz * $dz);
		if($dist > 8.0){
			$this->teleport($want);
			$this->motion = Vector3::zero();
			$this->lookAtLiving($holder);

			return;
		}
		if($dist < 0.12){
			$this->motion->x *= 0.25;
			$this->motion->z *= 0.25;

			return;
		}
		$speed = min(0.45, $dist * 0.18);
		$this->motion->x = ($dx / $dist) * $speed;
		$this->motion->z = ($dz / $dist) * $speed;
		$this->lookAtLiving($holder);
	}

	private function lookAtLiving(Living $target) : void{
		$eye = $target->getEyePos();
		$xDist = $eye->x - $this->location->x;
		$zDist = $eye->z - $this->location->z;
		$horizontal = sqrt($xDist * $xDist + $zDist * $zDist);
		if($horizontal < 0.28){
			$horizontal = 0.28;
		}
		$vertical = $eye->y - ($this->location->y + $this->getEyeHeight());
		$pitch = -atan2($vertical, $horizontal) / M_PI * 180;
		$maxPitch = 30.0;
		if($pitch > $maxPitch){
			$pitch = $maxPitch;
		}elseif($pitch < -$maxPitch){
			$pitch = -$maxPitch;
		}
		$yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;
		if($yaw < 0){
			$yaw += 360.0;
		}
		$this->setRotation($yaw, $pitch);
	}

	private function isHostileMob(Living $entity) : bool{
		return $entity instanceof Zombie
			|| $entity instanceof Skeleton
			|| $entity instanceof Creeper
			|| $entity instanceof Spider
			|| $entity instanceof Vindicator
			|| $entity instanceof Witch
			|| $entity instanceof WitherSkeleton
			|| $entity instanceof Blaze;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			return $hasUpdate;
		}

		$owner = $this->resolveOwner();
		if($this->tamed){
			if($owner !== null && $this->getOwningEntityId() !== $owner->getId()){
				$this->setOwningEntity($owner);
			}elseif($owner === null){
				$this->setOwningEntity(null);
			}
		}else{
			$this->setOwningEntity(null);
		}

		$angryMeta = $this->angryAtPlayer !== null || ($this->combatTarget instanceof Player && !$this->tamed);
		if($angryMeta !== $this->lastAngryMetadata){
			$this->lastAngryMetadata = $angryMeta;
			$this->networkPropertiesDirty = true;
		}

		if($this->angryTicksRemaining > 0){
			$this->angryTicksRemaining -= $tickDiff;
			if($this->angryTicksRemaining <= 0){
				$this->angryAtPlayer = null;
				if($this->combatTarget instanceof Player && !$this->tamed){
					$this->combatTarget = null;
					$this->setTargetEntity(null);
				}
			}
		}

		$this->validateCombatTarget();

		$leashHolder = $this->resolveLeashHolder();
		if($this->leashHolderUuid !== null){
			if($leashHolder === null){
				$this->leashHolderUuid = null;
				$this->networkPropertiesDirty = true;
			}elseif($leashHolder->getWorld() !== $this->getWorld()){
				$this->leashHolderUuid = null;
				$this->networkPropertiesDirty = true;
			}elseif(!$this->sitting){
				if($this->location->distanceSquared($leashHolder->getPosition()) > 100.0){
					$this->breakLeash(true);
				}else{
					$this->tickLeashFollow($leashHolder);
					$this->handleVanillaJump();
					if($this->attackCooldown > 0){
						$this->attackCooldown -= $tickDiff;
					}
					if($this->jumpCooldown > 0){
						$this->jumpCooldown -= $tickDiff;
					}
					if($this->obstacleAvoidCooldown > 0){
						$this->obstacleAvoidCooldown -= $tickDiff;
					}

					return true;
				}
			}
		}

		if($this->sitting && $this->tamed){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
			$this->motion->x = 0;
			$this->motion->z = 0;
			$this->smoothMotionX = 0.0;
			$this->smoothMotionZ = 0.0;
			if($this->attackCooldown > 0){
				$this->attackCooldown -= $tickDiff;
			}
			if($this->jumpCooldown > 0){
				$this->jumpCooldown -= $tickDiff;
			}
			if($this->obstacleAvoidCooldown > 0){
				$this->obstacleAvoidCooldown -= $tickDiff;
			}

			return true;
		}

		if($this->obstacleAvoidCooldown > 0){
			$this->obstacleAvoidCooldown -= $tickDiff;
		}

		if($this->combatTarget !== null){
			$this->combatAI();
		}elseif($this->tamed && $owner !== null){
			$this->guardOrFollowOwner($owner);
		}elseif($this->angryAtPlayer !== null && $this->angryAtPlayer->isAlive() && !$this->angryAtPlayer->isClosed()){
			$this->combatTarget = $this->angryAtPlayer;
			$this->setTargetEntity($this->angryAtPlayer);
			$this->combatAI();
		}elseif(!$this->tamed){
			$skeleton = $this->findNearestSkeleton(16.0);
			if($skeleton !== null){
				$this->combatTarget = $skeleton;
				$this->setTargetEntity($skeleton);
				$this->combatAI();
			}else{
				$this->wanderAI();
			}
		}else{
			$this->wanderAI();
		}

		$this->handleVanillaJump();

		if($this->attackCooldown > 0){
			$this->attackCooldown -= $tickDiff;
		}

		return true;
	}

	private function validateCombatTarget() : void{
		if($this->combatTarget === null){
			return;
		}
		if(!$this->combatTarget->isAlive() || $this->combatTarget->isClosed()){
			$this->combatTarget = null;
			$this->setTargetEntity(null);

			return;
		}
		if($this->location->distanceSquared($this->combatTarget->getPosition()) > 900){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
		}
	}

	private function findNearestSkeleton(float $range) : ?Skeleton{
		$bb = $this->getBoundingBox()->expandedCopy($range, $range, $range);
		$best = null;
		$bestD = $range * $range;
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $e){
			if($e instanceof Skeleton && $e->isAlive()){
				$d = $this->location->distanceSquared($e->getPosition());
				if($d < $bestD){
					$bestD = $d;
					$best = $e;
				}
			}
		}

		return $best;
	}

	private function findHostileNear(Vector3 $origin, float $range) : ?Living{
		$bb = new AxisAlignedBB(
			$origin->x - $range,
			$origin->y - $range,
			$origin->z - $range,
			$origin->x + $range,
			$origin->y + $range,
			$origin->z + $range
		);
		$best = null;
		$bestD = $range * $range;
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $e){
			if($e === $this || !$e instanceof Living || !$e->isAlive()){
				continue;
			}
			if(!$this->isHostileMob($e)){
				continue;
			}
			$d = $this->location->distanceSquared($e->getPosition());
			if($d < $bestD){
				$bestD = $d;
				$best = $e;
			}
		}

		return $best;
	}

	private function guardOrFollowOwner(Player $owner) : void{
		$threat = $this->findHostileNear($owner->getPosition(), 12.0) ?? $this->findHostileNear($this->location->asVector3(), 10.0);
		if($threat !== null){
			$this->combatTarget = $threat;
			$this->setTargetEntity($threat);
			$this->combatAI();

			return;
		}

		$this->combatTarget = null;
		$this->setTargetEntity(null);

		$o = $owner->getPosition();
		$distSq = $this->location->distanceSquared($o);
		if($distSq > 576){
			$safe = $o->add((mt_rand(-100, 100) / 100), 0, (mt_rand(-100, 100) / 100));
			$this->teleport($safe);
			$this->motion = new Vector3(0, $this->motion->y, 0);

			return;
		}
		if($distSq > 100){
			$this->moveTowards($o, true, true);

			return;
		}
		if($distSq < 4){
			$this->idleTime = max($this->idleTime, 20);
			$this->wanderAI();

			return;
		}
		$this->wanderAI();
	}

	private function handleVanillaJump() : void{
		if(!$this->onGround || $this->jumpCooldown > 0){
			if($this->jumpCooldown > 0){
				$this->jumpCooldown--;
			}

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
		$blockHead = $world->getBlockAt((int) floor($checkX), (int) floor($this->location->y + 1.2), (int) floor($checkZ));
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
		$jumpVelocity = $this->getJumpVelocity();
		$this->motion = new Vector3($dirX * 0.45, $jumpVelocity, $dirZ * 0.45);
		$this->jumpCooldown = 12;
	}

	private function combatAI() : void{
		$t = $this->combatTarget;
		if($t === null){
			return;
		}

		if($this->attackTime > 0){
			$this->lookAtLiving($t);

			return;
		}

		$targetPos = $t->getPosition();
		$this->lookAtLiving($t);
		$distSq = $this->location->distanceSquared($targetPos);

		if($distSq <= 2.25){
			$this->smoothMotionX *= 0.6;
			$this->smoothMotionZ *= 0.6;
			$this->motion->x = $this->smoothMotionX;
			$this->motion->z = $this->smoothMotionZ;
			$this->attackCombatTarget();

			return;
		}

		$dirX = $targetPos->x - $this->location->x;
		$dirZ = $targetPos->z - $this->location->z;
		$len = sqrt($dirX * $dirX + $dirZ * $dirZ);
		if($len > 0.01 && $this->obstacleAvoidCooldown <= 0 && $this->hasObstacleInFront($dirX / $len, $dirZ / $len, 0.7)){
			$avoid = $this->getAvoidanceDirection($dirX / $len, $dirZ / $len);
			if($avoid !== null){
				$strafe = 0.2;
				$this->smoothMotionX = $this->smoothMotionX * 0.5 + ($avoid->x * $strafe) * 0.5;
				$this->smoothMotionZ = $this->smoothMotionZ * 0.5 + ($avoid->z * $strafe) * 0.5;
				$this->motion->x = $this->smoothMotionX;
				$this->motion->z = $this->smoothMotionZ;
				$this->obstacleAvoidCooldown = 12;
			}else{
				$this->moveTowards($targetPos, true, false);
			}
		}else{
			$this->moveTowards($targetPos, true, false);
		}
	}

	private function attackCombatTarget() : void{
		if($this->attackCooldown > 0 || $this->combatTarget === null){
			return;
		}
		$this->broadcastAnimation(new ArmSwingAnimation($this));
		NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [
			AnimatePacket::create($this->getId(), AnimatePacket::ACTION_SWING_ARM),
		]);
		$ev = new EntityDamageByEntityEvent($this, $this->combatTarget, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 4.0);
		$this->combatTarget->attack($ev);
		$this->attackCooldown = 14;
	}

	private function hasObstacleInFront(float $dirX, float $dirZ, float $distance = 0.8) : bool{
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
		$yHead = (int) floor($this->location->y + 1.2);

		return $world->getBlockAt($x, $yFoot, $z)->isSolid() && $world->getBlockAt($x, $yHead, $z)->isSolid();
	}

	private function getAvoidanceDirection(float $dirX, float $dirZ) : ?Vector3{
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
		if(!$this->hasObstacleInFront($leftX, $leftZ, 0.85)){
			return new Vector3($leftX, 0, $leftZ);
		}
		if(!$this->hasObstacleInFront($rightX, $rightZ, 0.85)){
			return new Vector3($rightX, 0, $rightZ);
		}

		return null;
	}

	private function moveTowards(Vector3 $target, bool $smooth = true, bool $relaxPitchWhileWalking = false) : void{
		$x = $target->x - $this->location->x;
		$z = $target->z - $this->location->z;
		$diff = abs($x) + abs($z);
		if($diff < 0.1){
			if($smooth){
				$this->motion->x = $this->smoothMotionX *= 0.6;
				$this->motion->z = $this->smoothMotionZ *= 0.6;
			}
			if($relaxPitchWhileWalking){
				$this->location->pitch *= 0.8;
				if(abs($this->location->pitch) < 0.5){
					$this->location->pitch = 0;
				}
			}

			return;
		}
		$speed = 0.28;
		$wantX = $speed * ($x / $diff);
		$wantZ = $speed * ($z / $diff);
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
		if($relaxPitchWhileWalking){
			$this->location->pitch *= 0.84;
			if(abs($this->location->pitch) < 0.5){
				$this->location->pitch = 0;
			}
		}
	}

	private function lerpAngle(float $from, float $to, float $t) : float{
		$diff = $to - $from + 540.0;
		$wrapped = $diff - 360.0 * floor($diff / 360.0);
		$delta = $wrapped - 180.0;

		return $from + $delta * $t;
	}

	private function wanderAI() : void{
		if($this->idleTime > 0){
			$this->idleTime--;
			$this->motion->x = $this->smoothMotionX *= 0.85;
			$this->motion->z = $this->smoothMotionZ *= 0.85;

			return;
		}
		$motionLen = sqrt($this->motion->x ** 2 + $this->motion->z ** 2);
		if($this->wanderTarget !== null && $motionLen > 0.03 && $this->obstacleAvoidCooldown <= 0){
			$dirX = $this->motion->x / $motionLen;
			$dirZ = $this->motion->z / $motionLen;
			if($this->hasObstacleInFront($dirX, $dirZ, 0.65)){
				$avoid = $this->getAvoidanceDirection($dirX, $dirZ);
				if($avoid !== null){
					$this->wanderTarget = $this->location->add($avoid->x * 5, 0, $avoid->z * 5);
					$this->obstacleAvoidCooldown = 22;
					$this->idleTime = 6;
				}
			}
		}
		if($this->wanderTarget === null || $this->location->distanceSquared($this->wanderTarget) < 6.25){
			$this->idleTime = 70;
			$angle = mt_rand(0, 359) * (M_PI / 180);
			$dist = mt_rand(4, 10);
			$this->wanderTarget = $this->location->add(cos($angle) * $dist, 0, sin($angle) * $dist);
		}
		$this->moveTowards($this->wanderTarget, true, true);
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source->isCancelled() || !$this->isAlive()){
			return;
		}
		if($this->tamed){
			return;
		}
		if($source instanceof EntityDamageByEntityEvent){
			$d = $source->getDamager();
			if($d instanceof Player){
				$this->angryAtPlayer = $d;
				$this->angryTicksRemaining = 500;
				$this->combatTarget = $d;
				$this->setTargetEntity($d);
				$this->networkPropertiesDirty = true;
			}
		}
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();

		if($this->tamed){
			if($this->isOwner($player)){
				if($item instanceof Dye){
					$this->collarColor = DyeColorIdMap::getInstance()->toId($item->getColor());
					$item->pop();
					$player->getInventory()->setItemInHand($item);
					$this->networkPropertiesDirty = true;

					return true;
				}
				if($item->isNull() || $item->getTypeId() === VanillaItems::AIR()->getTypeId()){
					$this->sitting = !$this->sitting;
					$this->networkPropertiesDirty = true;

					return true;
				}
			}

			return false;
		}

		if($item->getTypeId() === VanillaItems::BONE()->getTypeId()){
			$item->pop();
			$player->getInventory()->setItemInHand($item);
			if(mt_rand(1, 3) === 1){
				$this->tamed = true;
				$this->ownerUuid = $player->getUniqueId()->toString();
				$this->setOwningEntity($player);
				$this->angryAtPlayer = null;
				$this->combatTarget = null;
				$this->setTargetEntity(null);
				$this->angryTicksRemaining = 0;
				$this->collarColor = DyeColorIdMap::getInstance()->toId(DyeColor::RED);
				$this->networkPropertiesDirty = true;
				$world = $this->getWorld();
				for($i = 0; $i < 7; $i++){
					$world->addParticle($this->location->add(0, 0.5 + mt_rand(0, 10) / 20, 0), new HeartParticle());
				}
			}

			return true;
		}

		return false;
	}

	public function getDrops() : array{
		return [];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::WOLF_SPAWN_EGG();
	}

	public function isBaby() : bool{
		return false;
	}
}
