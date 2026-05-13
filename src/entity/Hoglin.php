<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\ai\Goal;
use pocketmine\entity\ai\GoalExecutor;
use pocketmine\entity\ai\Sensor;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\cache\ChunkCache;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use function cos;
use function deg2rad;
use function floor;
use function max;
use function mt_rand;
use function sin;
use function sqrt;

class Hoglin extends Monster implements Ageable{
	private const TAG_AGE = "Age";
	private const TAG_AGE_LOCKED = "AgeLocked";
	private const TAG_SPAWN_PROTECTION = "SpawnProtectionTicks";
	private const TAG_OVERWORLD_CONVERSION_TICKS = "OverworldConversionTicks";
	private const BABY_AGE = -24000;
	private const CONVERSION_TICKS = 300;

	private int $age = 0;
	private bool $ageLocked = false;
	private int $spawnProtectionTicks = 200;
	private int $overworldConversionTicks = 0;

	private ?Living $combatTarget = null;
	private ?Vector3 $wanderTarget = null;
	private int $idleTime = 0;

	private int $wallBashTicks = 0;
	private int $wallBashDirectionCooldown = 0;
	private float $wallBashYaw = 0.0;
	private bool $lastAngryState = false;
	private bool $lastBabyState = false;

	private GoalExecutor $goalExecutor;

	public static function getNetworkTypeId() : string{
		return EntityIds::HOGLIN;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.4, 0.9);
	}

	public function getName() : string{
		return "Hoglin";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(40);
		$this->setHealth(min($this->getHealth(), 40.0));
		$this->setStepHeight(1.0);

		$this->age = $nbt->getInt(self::TAG_AGE, 0);
		$this->ageLocked = $nbt->getByte(self::TAG_AGE_LOCKED, 0) !== 0;
		$this->spawnProtectionTicks = max(0, $nbt->getInt(self::TAG_SPAWN_PROTECTION, 200));
		$this->overworldConversionTicks = max(0, $nbt->getInt(self::TAG_OVERWORLD_CONVERSION_TICKS, 0));
		$this->lastBabyState = $this->isBaby();

		$this->goalExecutor = $this->createGoalExecutor();
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_AGE, $this->age);
		$nbt->setByte(self::TAG_AGE_LOCKED, $this->ageLocked ? 1 : 0);
		$nbt->setInt(self::TAG_SPAWN_PROTECTION, $this->spawnProtectionTicks);
		$nbt->setInt(self::TAG_OVERWORLD_CONVERSION_TICKS, $this->overworldConversionTicks);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->isBaby());
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->combatTarget !== null);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			return $hasUpdate;
		}

		if(!$this->ageLocked && $this->age < 0){
			$this->age = min(0, $this->age + $tickDiff);
		}
		if($this->spawnProtectionTicks > 0){
			$this->spawnProtectionTicks = max(0, $this->spawnProtectionTicks - $tickDiff);
		}
		$this->tickOverworldConversion($tickDiff);
		if($this->isFlaggedForDespawn()){
			return true;
		}

		if($this->tickWaterLock($tickDiff, 0.035, -0.26)){
			$this->updateStateMetadata();
			return true;
		}
		if($this->tickPostHitMovementPause($tickDiff)){
			$this->tickAiCooldowns($tickDiff);
			$this->updateStateMetadata();
			return true;
		}

		$this->validateCombatTarget();
		$this->goalExecutor->tick($this, $tickDiff);

		$this->handleVanillaJump(0.42);
		$this->tickAiCooldowns($tickDiff);
		$this->updateStateMetadata();

		return true;
	}

	public function isBaby() : bool{
		return $this->age < 0;
	}

	public function setBaby(bool $baby = true) : void{
		$this->age = $baby ? self::BABY_AGE : 0;
	}

	public function setAgeLocked(bool $locked) : void{
		$this->ageLocked = $locked;
	}

	public function hasWallBashMode() : bool{
		return $this->wallBashTicks > 0;
	}

	public function tickWallBash(int $tickDiff = 1) : void{
		$this->wallBashTicks = max(0, $this->wallBashTicks - $tickDiff);
		$this->wallBashDirectionCooldown = max(0, $this->wallBashDirectionCooldown - $tickDiff);
		if($this->wallBashTicks <= 0){
			return;
		}

		if($this->wallBashDirectionCooldown <= 0){
			$this->wallBashYaw = (float) mt_rand(0, 359);
			$this->wallBashDirectionCooldown = 6;
		}

		$rad = deg2rad($this->wallBashYaw);
		$dirX = -sin($rad);
		$dirZ = cos($rad);

		if($this->hasObstacleInFront($dirX, $dirZ, 0.7) && $this->onGround){
			$this->jump();
			$this->motion->y = max($this->motion->y, 0.32);
			$this->broadcastAnimation(new ArmSwingAnimation($this));
		}

		$this->moveByDirection($dirX, $dirZ, $this->isBaby() ? 0.26 : 0.34, true, true);
	}

	public function tickFleeHazard(Vector3 $hazard, float $adultSpeed = 0.36, float $babySpeed = 0.28) : void{
		$this->combatTarget = null;
		$this->setTargetEntity(null);

		$dx = $this->location->x - $hazard->x;
		$dz = $this->location->z - $hazard->z;
		$len = sqrt($dx * $dx + $dz * $dz);
		if($len < 0.01){
			$this->wallBashYaw = (float) mt_rand(0, 359);
			$rad = deg2rad($this->wallBashYaw);
			$dx = -sin($rad);
			$dz = cos($rad);
			$len = 1.0;
		}
		$this->moveByDirection($dx / $len, $dz / $len, $this->isBaby() ? $babySpeed : $adultSpeed, true, true);
	}

	public function tickCombat(Living $target) : void{
		if($this->spawnProtectionTicks > 0){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
			return;
		}
		if(!$this->canTargetEntity($target)){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
			return;
		}

		$this->combatTarget = $target;
		$this->setTargetEntity($target);
		$this->lookAt($target->getEyePos());

		$distSq = $this->location->distanceSquared($target->getPosition());
		if($distSq <= 3.24){
			$this->motion->x = $this->smoothMotionX *= 0.55;
			$this->motion->z = $this->smoothMotionZ *= 0.55;
			$this->attackLivingTarget($target);
			return;
		}

		$this->moveTowardsPoint($target->getPosition(), $this->isBaby() ? 0.22 : 0.29, true, true);
	}

	public function tickWander() : void{
		$this->combatTarget = null;
		$this->setTargetEntity(null);

		if($this->idleTime > 0){
			$this->idleTime--;
			$this->motion->x = $this->smoothMotionX *= 0.82;
			$this->motion->z = $this->smoothMotionZ *= 0.82;
			return;
		}

		if($this->wanderTarget === null || $this->location->distanceSquared($this->wanderTarget) < 6.0){
			$angle = mt_rand(0, 359);
			$dist = mt_rand(4, 10);
			$rad = deg2rad((float) $angle);
			$this->wanderTarget = $this->location->add(cos($rad) * $dist, 0, sin($rad) * $dist);
			$this->idleTime = mt_rand(10, 50);
		}

		$this->moveTowardsPoint($this->wanderTarget, $this->isBaby() ? 0.18 : 0.22, true, true);
	}

	public function detectNearestHazard(float $range) : ?Vector3{
		$world = $this->getWorld();
		$baseX = (int) floor($this->location->x);
		$baseY = (int) floor($this->location->y);
		$baseZ = (int) floor($this->location->z);
		$r = (int) floor($range);
		$best = null;
		$bestDist = $range * $range;

		for($x = $baseX - $r; $x <= $baseX + $r; ++$x){
			for($z = $baseZ - $r; $z <= $baseZ + $r; ++$z){
				for($y = $baseY - 2; $y <= $baseY + 2; ++$y){
					$typeId = $world->getBlockAt($x, $y, $z)->getTypeId();
					if(
						$typeId !== BlockTypeIds::LAVA &&
						$typeId !== BlockTypeIds::FIRE &&
						$typeId !== BlockTypeIds::SOUL_FIRE &&
						$typeId !== BlockTypeIds::MAGMA &&
						$typeId !== BlockTypeIds::CAMPFIRE &&
						$typeId !== BlockTypeIds::SOUL_CAMPFIRE
					){
						continue;
					}
					$dx = $x + 0.5 - $this->location->x;
					$dy = $y + 0.5 - $this->location->y;
					$dz = $z + 0.5 - $this->location->z;
					$distSq = $dx * $dx + $dy * $dy + $dz * $dz;
					if($distSq < $bestDist){
						$bestDist = $distSq;
						$best = new Vector3($x + 0.5, $y + 0.5, $z + 0.5);
					}
				}
			}
		}

		return $best;
	}

	public function detectNearestRepellent(float $range) : ?Vector3{
		$world = $this->getWorld();
		$baseX = (int) floor($this->location->x);
		$baseY = (int) floor($this->location->y);
		$baseZ = (int) floor($this->location->z);
		$r = (int) floor($range);
		$best = null;
		$bestDist = $range * $range;

		for($x = $baseX - $r; $x <= $baseX + $r; ++$x){
			for($z = $baseZ - $r; $z <= $baseZ + $r; ++$z){
				for($y = $baseY - 2; $y <= $baseY + 2; ++$y){
					$typeId = $world->getBlockAt($x, $y, $z)->getTypeId();
					if(
						$typeId !== BlockTypeIds::WARPED_FUNGUS &&
						$typeId !== BlockTypeIds::RESPAWN_ANCHOR &&
						$typeId !== BlockTypeIds::NETHER_PORTAL
					){
						continue;
					}
					$dx = $x + 0.5 - $this->location->x;
					$dy = $y + 0.5 - $this->location->y;
					$dz = $z + 0.5 - $this->location->z;
					$distSq = $dx * $dx + $dy * $dy + $dz * $dz;
					if($distSq < $bestDist){
						$bestDist = $distSq;
						$best = new Vector3($x + 0.5, $y + 0.5, $z + 0.5);
					}
				}
			}
		}

		return $best;
	}

	public function detectCombatTarget(float $range) : ?Living{
		if($this->spawnProtectionTicks > 0){
			return null;
		}
		if($this->combatTarget !== null && $this->canTargetEntity($this->combatTarget) && $this->location->distanceSquared($this->combatTarget->getPosition()) <= $range * $range){
			return $this->combatTarget;
		}

		$bb = new AxisAlignedBB(
			$this->location->x - $range,
			$this->location->y - 4,
			$this->location->z - $range,
			$this->location->x + $range,
			$this->location->y + 4,
			$this->location->z + $range
		);
		$best = null;
		$bestDist = $range * $range;
		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!$entity instanceof Living || !$this->canTargetEntity($entity)){
				continue;
			}
			$distSq = $this->location->distanceSquared($entity->getPosition());
			if($distSq < $bestDist){
				$bestDist = $distSq;
				$best = $entity;
			}
		}

		return $best;
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if($source->isCancelled() || !$this->isAlive()){
			return;
		}

		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Living && $this->canTargetEntity($damager)){
				$this->spawnProtectionTicks = 0;
				$this->combatTarget = $damager;
				$this->setTargetEntity($damager);
				$this->alertNearbyHoglins($damager);
			}
		}

		if($this->wallBashTicks <= 0 && mt_rand(1, 100) <= 30){
			$this->wallBashTicks = 40;
			$this->wallBashDirectionCooldown = 0;
			$this->wallBashYaw = (float) mt_rand(0, 359);
		}
	}

	public function getDrops() : array{
		if($this->isBaby()){
			return [];
		}

		$pork = $this->isOnFire() ? VanillaItems::COOKED_PORKCHOP() : VanillaItems::RAW_PORKCHOP();
		$drops = [$pork->setCount(mt_rand(2, 4))];
		$leatherCount = mt_rand(0, 1);
		if($leatherCount > 0){
			$drops[] = VanillaItems::LEATHER()->setCount($leatherCount);
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return $this->isBaby() ? 0 : mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::HOGLIN_SPAWN_EGG();
	}

	private function createGoalExecutor() : GoalExecutor{
		return new GoalExecutor(
			[
				new class implements Sensor{
					public function collect(Living $entity, array &$memory) : void{
						if(!$entity instanceof Hoglin){
							return;
						}
						$cooldown = (int) ($memory["repellent_scan_cooldown"] ?? 0);
						if($cooldown > 0){
							$memory["repellent_scan_cooldown"] = $cooldown - 1;
							return;
						}
						$memory["repellent"] = $entity->detectNearestRepellent(10.0);
						$memory["repellent_scan_cooldown"] = 4;
					}
				},
				new class implements Sensor{
					public function collect(Living $entity, array &$memory) : void{
						if(!$entity instanceof Hoglin){
							return;
						}
						$cooldown = (int) ($memory["hazard_scan_cooldown"] ?? 0);
						if($cooldown > 0){
							$memory["hazard_scan_cooldown"] = $cooldown - 1;
							return;
						}
						$memory["hazard"] = $entity->detectNearestHazard(8.0);
						$memory["hazard_scan_cooldown"] = 4;
					}
				},
				new class implements Sensor{
					public function collect(Living $entity, array &$memory) : void{
						if(!$entity instanceof Hoglin){
							return;
						}
						$cooldown = (int) ($memory["target_scan_cooldown"] ?? 0);
						if($cooldown > 0){
							$memory["target_scan_cooldown"] = $cooldown - 1;
							return;
						}
						$memory["target"] = $entity->detectCombatTarget(12.0);
						$memory["target_scan_cooldown"] = 2;
					}
				}
			],
			[
				new class implements Goal{
					public function getPriority() : int{
						return 100;
					}

					public function canRun(Living $entity, array $memory) : bool{
						return $entity instanceof Hoglin && $entity->hasWallBashMode();
					}

					public function tick(Living $entity, array $memory, int $tickDiff) : void{
						if($entity instanceof Hoglin){
							$entity->tickWallBash($tickDiff);
						}
					}
				},
				new class implements Goal{
					public function getPriority() : int{
						return 95;
					}

					public function canRun(Living $entity, array $memory) : bool{
						return $entity instanceof Hoglin && ($memory["repellent"] ?? null) instanceof Vector3;
					}

					public function tick(Living $entity, array $memory, int $tickDiff) : void{
						if($entity instanceof Hoglin && ($memory["repellent"] ?? null) instanceof Vector3){
							$entity->tickFleeHazard($memory["repellent"], 0.40, 0.30);
						}
					}
				},
				new class implements Goal{
					public function getPriority() : int{
						return 90;
					}

					public function canRun(Living $entity, array $memory) : bool{
						return $entity instanceof Hoglin && ($memory["hazard"] ?? null) instanceof Vector3;
					}

					public function tick(Living $entity, array $memory, int $tickDiff) : void{
						if($entity instanceof Hoglin && ($memory["hazard"] ?? null) instanceof Vector3){
							$entity->tickFleeHazard($memory["hazard"], 0.36, 0.28);
						}
					}
				},
				new class implements Goal{
					public function getPriority() : int{
						return 80;
					}

					public function canRun(Living $entity, array $memory) : bool{
						return $entity instanceof Hoglin && ($memory["target"] ?? null) instanceof Living;
					}

					public function tick(Living $entity, array $memory, int $tickDiff) : void{
						if($entity instanceof Hoglin && ($memory["target"] ?? null) instanceof Living){
							$entity->tickCombat($memory["target"]);
						}
					}
				},
				new class implements Goal{
					public function getPriority() : int{
						return 10;
					}

					public function canRun(Living $entity, array $memory) : bool{
						return $entity instanceof Hoglin;
					}

					public function tick(Living $entity, array $memory, int $tickDiff) : void{
						if($entity instanceof Hoglin){
							$entity->tickWander();
						}
					}
				}
			]
		);
	}

	private function attackLivingTarget(Living $target) : void{
		if($this->attackCooldown > 0){
			return;
		}

		$this->broadcastAnimation(new ArmSwingAnimation($this));
		$damage = $this->isBaby() ? 2.0 : 6.0;
		$knockback = $this->isBaby() ? 0.45 : 0.85;
		$ev = new EntityDamageByEntityEvent(
			$this,
			$target,
			EntityDamageEvent::CAUSE_ENTITY_ATTACK,
			$damage,
			[],
			$knockback,
			0.55
		);
		$target->attack($ev);
		$this->attackCooldown = 20;
	}

	private function updateStateMetadata() : void{
		$angry = $this->combatTarget !== null;
		$baby = $this->isBaby();
		if($angry !== $this->lastAngryState || $baby !== $this->lastBabyState){
			$this->lastAngryState = $angry;
			$this->lastBabyState = $baby;
			$this->networkPropertiesDirty = true;
		}
	}

	private function validateCombatTarget() : void{
		if($this->combatTarget === null){
			return;
		}
		if(!$this->canTargetEntity($this->combatTarget) || $this->location->distanceSquared($this->combatTarget->getPosition()) > 196){
			$this->combatTarget = null;
			$this->setTargetEntity(null);
		}
	}

	private function canTargetEntity(Living $entity) : bool{
		if($entity === $this || !$entity->isAlive() || $entity->isClosed()){
			return false;
		}

		if($entity instanceof Player){
			return !$entity->isCreative() && !$entity->isSpectator();
		}

		$typeId = $entity::getNetworkTypeId();
		if($typeId === EntityIds::WITHER_SKELETON){
			return true;
		}
		if($typeId === EntityIds::PIGLIN_BRUTE){
			return false;
		}
		if($typeId === EntityIds::PIGLIN){
			return !($entity instanceof Ageable && $entity->isBaby());
		}
		if($entity instanceof Pig){
			return !$entity->isBaby();
		}
		if($typeId === EntityIds::PIG){
			return !($entity instanceof Ageable && $entity->isBaby());
		}

		return false;
	}

	private function tickOverworldConversion(int $tickDiff) : void{
		if(ChunkCache::getDimensionIdForWorld($this->getWorld()) === DimensionIds::NETHER){
			$this->overworldConversionTicks = 0;
			return;
		}

		$this->overworldConversionTicks += $tickDiff;
		if($this->overworldConversionTicks >= self::CONVERSION_TICKS){
			$this->convertToZoglin();
		}
	}

	private function convertToZoglin() : void{
		if($this->isFlaggedForDespawn() || $this->isClosed()){
			return;
		}

		$zoglin = new Zoglin(Location::fromObject($this->location, $this->getWorld(), $this->location->yaw, $this->location->pitch), null);
		$zoglin->setBaby($this->isBaby());
		$zoglin->setAgeLocked($this->ageLocked);
		$zoglin->setHealth(min($zoglin->getMaxHealth(), $this->getHealth()));
		$zoglin->setFireTicks($this->getFireTicks());
		$zoglin->setNameTag($this->getNameTag());
		$zoglin->setNameTagVisible($this->isNameTagVisible());
		$zoglin->setNameTagAlwaysVisible($this->isNameTagAlwaysVisible());
		$zoglin->setScale($this->getScale());
		$zoglin->setMotion($this->getMotion());
		$zoglin->spawnToAll();
		$this->flagForDespawn();
	}

	private function alertNearbyHoglins(Living $target) : void{
		$range = 12.0;
		$bb = new AxisAlignedBB(
			$this->location->x - $range,
			$this->location->y - 4,
			$this->location->z - $range,
			$this->location->x + $range,
			$this->location->y + 4,
			$this->location->z + $range
		);

		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!$entity instanceof Hoglin || !$entity->isAlive() || $entity->isClosed()){
				continue;
			}
			if(!$entity->canTargetEntity($target)){
				continue;
			}
			$entity->spawnProtectionTicks = 0;
			$entity->combatTarget = $target;
			$entity->setTargetEntity($target);
		}
	}
}

