<?php


/*
 *
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
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\entity\mob;

use pocketmine\entity\animation\EnderDragonDeathAnimation;
use pocketmine\entity\animation\HurtAnimation;
use pocketmine\entity\BossBarEntity;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\Location;
use pocketmine\entity\object\AreaEffectCloud;
use pocketmine\entity\object\EndCrystal;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\generator\end\TheEndGenerator;
use pocketmine\world\particle\DragonBreathParticle;
use pocketmine\world\sound\EnderDragonDeathSound;
use pocketmine\world\sound\EnderDragonFlapSound;
use pocketmine\world\sound\EndPortalSpawnSound;
use pocketmine\world\sound\ExplodeSound;
use pocketmine\world\World;
use function atan2;
use function cos;
use function deg2rad;
use function max;
use function min;
use function M_PI;
use function sin;
use function spl_object_id;
use function sqrt;

class EnderDragon extends Living implements BossBarEntity, NeverSavedWithChunkEntity{

	public const PORTAL_X = 0;
	public const PORTAL_Z = 0;

	private const ORBIT_RADIUS_OUTSIDE = 56.0;
	private const ORBIT_RADIUS_INSIDE = 20.0;
	private const FLY_HEIGHT = 85.0;
	private const FLY_SPEED = 0.65;
	private const YAW_OFFSET = 180.0;
	private const BOSS_BAR_OVERLAY = 0;
	private const PERCH_Y_OFFSET = 4.0;
	private const PILLAR_CLEARANCE_H = 9.0;
	private const PILLAR_CLEARANCE_V = 6.0;
	private const CRYSTAL_COUNT_CACHE_TICKS = 40;

	private const PHASE_CIRCLING = 0;
	private const PHASE_STRAFING = 1;
	private const PHASE_LANDING_APPROACH = 2;
	private const PHASE_LANDING = 3;
	private const PHASE_TAKEOFF = 4;
	private const PHASE_SITTING_FLAMING = 5;
	private const PHASE_CHARGING = 8;
	private const PHASE_DYING = 9;

	private const MAX_BREATH_CYCLES = 4;
	private const BREATH_CYCLE_TICKS = 200;
	private const ROAR_TICKS = 40;
	private const FLAME_ACTIVE_TICKS = 10;
	private const SMOKE_RADIUS = 3.33;
	private const SMOKE_DURATION = 133;
	private const PERCH_DAMAGE_ESCAPE = 50.0;
	private const CRYSTAL_HEAL_INTERVAL = 10;
	private const CRYSTAL_HEAL_AMOUNT = 1.0;

	private int $phase = self::PHASE_CIRCLING;
	private float $circleAngle = 0.0;
	private int $aiTimer = 0;
	private int $perchTimer = 0;
	private int $breathCooldown = 0;
	private int $flapCooldown = 0;
	private int $breathAttackCount = 0;
	private float $sittingDamageReceived = 0.0;
	private int $flightStage = 0;
	private float $landingStartDist = 0.0;
	private float $landingStartY = 0.0;
	private bool $deathAnimationPlayed = false;

	private float $smoothYaw = 0.0;
	private float $smoothPitch = 0.0;

	/** @var array<int, true> */
	private array $bossBarPlayers = [];

	/** @var array<int, int> */
	private array $bossBarResendDelays = [];

	/** @var array<int, array{centerX: int, centerZ: int, radius: int, height: int, guarded: bool}>|null */
	private ?array $obsidianPillars = null;

	private int $cachedCrystalCount = 0;
	private int $crystalCountCacheTick = -1000;

	public static function getNetworkTypeId() : string{
		return EntityIds::ENDER_DRAGON;
	}

	public static function isAliveInWorld(World $world) : bool{
		foreach($world->getEntities() as $entity){
			if($entity instanceof self && $entity->isAlive()){
				return true;
			}
		}
		return false;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(4.0, 13.0);
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	public function getName() : string{
		return "Ender Dragon";
	}

	public function isFireProof() : bool{
		return true;
	}

	public function canCollideWith(Entity $entity) : bool{
		return false;
	}

	public function hasMovementUpdate() : bool{
		return $this->isAlive() || parent::hasMovementUpdate();
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$savedHealth = $this->getHealth();
		$this->setMaxHealth(200);
		if($nbt->getTag("Health") === null && $nbt->getTag("HealF") === null){
			$this->setHealth(200);
		}else{
		$this->setHealth(min($savedHealth, 200));
		}
		$this->setHasGravity(false);
		$this->setNoClientPredictions(true);
		$this->knockbackResistanceAttr->setValue(1.0);
		$this->phase = self::PHASE_CIRCLING;
		$this->circleAngle = atan2($this->location->z, max(0.01, abs($this->location->x)));
		$this->smoothYaw = $this->location->yaw;
		$this->smoothPitch = $this->location->pitch;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::HAS_COLLISION, false);
		$properties->setGenericFlag(EntityMetadataFlags::NO_AI, false);
		$properties->setInt(EntityMetadataProperties::VARIANT, $this->phase);
	}

	public function getBossBarTitle() : string{
		return "Ender Dragon";
	}

	public function getBossBarHealthPercent() : float{
		$max = max(1, $this->getMaxHealth());
		return max(0.0, min(1.0, $this->getHealth() / $max));
	}

	private function createBossBarPacket(int $eventType, Player $player, ?float $healthPercent = null) : BossEventPacket{
		$health = $healthPercent ?? $this->getBossBarHealthPercent();
		$pk = BossEventPacket::show(
			$this->getId(),
			$this->getBossBarTitle(),
			$health,
			darkenScreen: false,
			color: BossBarColor::PURPLE,
			overlay: self::BOSS_BAR_OVERLAY
		);
		$pk->eventType = $eventType;
		$pk->playerActorUniqueId = $player->getId();
		$pk->title = $this->getBossBarTitle();
		$pk->filteredTitle = $this->getBossBarTitle();
		$pk->healthPercent = $health;
		$pk->color = BossBarColor::PURPLE;
		$pk->overlay = self::BOSS_BAR_OVERLAY;
		return $pk;
	}

	private function createBossBarHealthPacket(Player $player) : BossEventPacket{
		$health = $this->getBossBarHealthPercent();
		$pk = BossEventPacket::healthPercent($this->getId(), $health);
		$pk->playerActorUniqueId = $player->getId();
		$pk->title = $this->getBossBarTitle();
		$pk->filteredTitle = $this->getBossBarTitle();
		$pk->healthPercent = $health;
		$pk->color = BossBarColor::PURPLE;
		$pk->overlay = self::BOSS_BAR_OVERLAY;
		return $pk;
	}

	public function sendBossBarTo(Player $player) : void{
		$player->getNetworkSession()->sendDataPacket(
			$this->createBossBarPacket(BossEventPacket::TYPE_SHOW, $player)
		);
		$this->bossBarPlayers[spl_object_id($player)] = true;
	}

	private function queueBossBarResend(Player $player, int $delayTicks = 15) : void{
		$this->bossBarResendDelays[spl_object_id($player)] = $delayTicks;
	}

	private function tickBossBarResends(int $tickDiff) : void{
		foreach($this->bossBarResendDelays as $playerId => $remaining){
			$remaining -= $tickDiff;
			if($remaining > 0){
				$this->bossBarResendDelays[$playerId] = $remaining;
				continue;
			}
			unset($this->bossBarResendDelays[$playerId]);
			foreach($this->hasSpawned as $player){
				if(spl_object_id($player) === $playerId){
					$this->sendBossBarTo($player);
					break;
				}
			}
		}
	}

	public function setHealth(float $amount) : void{
		$wasAlive = $this->isAlive();
		parent::setHealth($amount);
		if($wasAlive){
			$this->broadcastBossBarHealth();
		}
	}

	public function heal(EntityRegainHealthEvent $source) : void{
		$wasAlive = $this->isAlive();
		parent::heal($source);
		if($wasAlive && $this->isAlive()){
			$this->broadcastBossBarHealth();
		}
	}

	public function spawnTo(Player $player) : void{
		parent::spawnTo($player);
		$this->sendBossBarTo($player);
		$this->queueBossBarResend($player);
	}

	public function despawnFrom(Player $player, bool $send = true) : void{
		unset($this->bossBarPlayers[spl_object_id($player)], $this->bossBarResendDelays[spl_object_id($player)]);
		if($send){
			$pk = BossEventPacket::hide($this->getId());
			$pk->playerActorUniqueId = $player->getId();
			$player->getNetworkSession()->sendDataPacket($pk);
		}
		parent::despawnFrom($player, $send);
	}

	private function broadcastBossBarHealth() : void{
		foreach($this->hasSpawned as $player){
			if(!isset($this->bossBarPlayers[spl_object_id($player)])){
				continue;
			}
			$player->getNetworkSession()->sendDataPacket(
				$this->createBossBarHealthPacket($player)
			);
		}
	}

	private function hideBossBarForAll() : void{
		foreach($this->hasSpawned as $player){
			$pk = BossEventPacket::hide($this->getId());
			$pk->playerActorUniqueId = $player->getId();
			$player->getNetworkSession()->sendDataPacket($pk);
		}
		$this->bossBarPlayers = [];
	}

	private function tickBossBarForEndPlayers() : void{
		if(!$this->isAlive() || $this->phase === self::PHASE_DYING){
			return;
		}
		foreach($this->getWorld()->getPlayers() as $player){
			if(!$player->isConnected() || $player->getWorld() !== $this->getWorld()){
				continue;
			}
			if(!isset($this->bossBarPlayers[spl_object_id($player)])){
				$this->sendBossBarTo($player);
			}
		}
	}

	private function setPhase(int $phase) : void{
		if($this->phase === $phase){
			return;
		}
		$this->phase = $phase;
		$this->networkPropertiesDirty = true;
	}

	public function attack(EntityDamageEvent $source) : void{
		$wasAlive = $this->isAlive();
		$healthBefore = $this->getHealth();
		parent::attack($source);

		if(!$wasAlive || !$this->isAlive() || $source->isCancelled()){
			return;
		}

		$damage = max(0.0, $healthBefore - $this->getHealth());
		if($damage <= 0){
			return;
		}

		$this->broadcastBossBarHealth();

		if($this->phase === self::PHASE_SITTING_FLAMING){
			$this->sittingDamageReceived += $damage;
			return;
		}

		if(
			$this->phase === self::PHASE_CIRCLING &&
			$this->countAliveCrystals() > 0 &&
			$this->getHealth() < $this->getMaxHealth()
		){
			$this->beginLandingApproach();
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if(!$this->isAlive()){
			return parent::entityBaseTick($tickDiff);
		}

		$this->getWorld()->loadChunk(0, 0);
		if(($this->aiTimer & 19) === 0){
			$this->getWorld()->loadChunk($this->location->getFloorX() >> 4, $this->location->getFloorZ() >> 4);
		}

		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->phase === self::PHASE_DYING){
			return $hasUpdate;
		}

		$this->onGround = false;
		$this->setNoClientPredictions(true);
		$this->setForceMovementUpdate(true);
		$this->aiTimer += $tickDiff;

		if($this->breathCooldown > 0){
			$this->breathCooldown -= $tickDiff;
		}
		if($this->flapCooldown > 0){
			$this->flapCooldown -= $tickDiff;
		}

		$this->tickBossBarResends($tickDiff);

		if($this->ticksLived % 20 === 0){
			$this->tickBossBarForEndPlayers();
			if(count($this->bossBarPlayers) > 0){
				$this->broadcastBossBarHealth();
			}
		}

		if(intdiv($this->aiTimer, self::CRYSTAL_HEAL_INTERVAL) > intdiv($this->aiTimer - $tickDiff, self::CRYSTAL_HEAL_INTERVAL)){
			$this->healFromCrystalsOnce();
		}

		if($this->phase !== self::PHASE_DYING){
			$this->updateCrystalBeams($this->getHealth() < $this->getMaxHealth());
		}

		match($this->phase){
			self::PHASE_CIRCLING => $this->tickCircling($tickDiff),
			self::PHASE_STRAFING => $this->tickStrafing($tickDiff),
			self::PHASE_LANDING_APPROACH, self::PHASE_LANDING => $this->tickLanding($tickDiff),
			self::PHASE_SITTING_FLAMING => $this->tickPerching($tickDiff),
			self::PHASE_TAKEOFF => $this->tickTakeoff($tickDiff),
			self::PHASE_CHARGING => $this->tickCharging($tickDiff),
			default => $this->tickCircling($tickDiff),
		};

		return true;
	}

	private function getOrbitRadius() : float{
		return $this->countAliveCrystals() > 0 ? self::ORBIT_RADIUS_OUTSIDE : self::ORBIT_RADIUS_INSIDE;
	}

	private function tickCircling(int $tickDiff) : void{
		$this->setPhase(self::PHASE_CIRCLING);
		$this->circleAngle += 0.008 * $tickDiff;
		$radius = $this->getOrbitRadius();
		$target = new Vector3(
			cos($this->circleAngle) * $radius,
			self::FLY_HEIGHT + sin($this->circleAngle * 1.5) * 3.0,
			sin($this->circleAngle) * $radius
		);
		$this->flyTowards($target, self::FLY_SPEED, $tickDiff);
		$this->tryFlapWings();

		if($this->circleAngle >= M_PI * 2){
			$this->circleAngle -= M_PI * 2;
			$this->tryVanillaPerchRoll();
		}

		if($this->aiTimer % 60 === 0){
			$this->tryAggroPlayer();
		}
	}

	private function tryVanillaPerchRoll() : void{
		$crystals = $this->countAliveCrystals();
		$rollBound = $this->getHealth() < $this->getMaxHealth()
			? max(1, 2 + $crystals)
			: ($crystals > 0 ? 5 + $crystals : 7);
		if(mt_rand(0, $rollBound) !== 0){
			return;
		}
		$this->beginLandingApproach();
	}

	private function beginLandingApproach() : void{
		if(
			$this->phase === self::PHASE_LANDING_APPROACH ||
			$this->phase === self::PHASE_LANDING ||
			$this->phase === self::PHASE_SITTING_FLAMING ||
			$this->phase === self::PHASE_TAKEOFF
		){
			return;
		}
		$this->flightStage = 0;
		$this->landingStartDist = 0.0;
		$this->landingStartY = 0.0;
		$this->breathAttackCount = 0;
		$this->sittingDamageReceived = 0.0;
		$this->setPhase(self::PHASE_LANDING_APPROACH);
	}

	private function tickStrafing(int $tickDiff) : void{
		$this->setPhase(self::PHASE_STRAFING);
		$this->circleAngle += 0.01 * $tickDiff;
		$radius = $this->getOrbitRadius();
		$target = new Vector3(
			cos($this->circleAngle) * $radius,
			self::FLY_HEIGHT + 4.0,
			sin($this->circleAngle) * $radius
		);
		$this->flyTowards($target, self::FLY_SPEED * 1.1, $tickDiff);
		$this->tryFlapWings();

		if($this->aiTimer % 160 === 0){
			$this->setPhase(self::PHASE_CIRCLING);
		}
	}

	private function tickLanding(int $tickDiff) : void{
		$perch = $this->getPerchPosition();
		$dx = $this->location->x - $perch->x;
		$dz = $this->location->z - $perch->z;
		$horizDist = sqrt(($dx * $dx) + ($dz * $dz));

		if($this->landingStartDist <= 0.0){
			$this->landingStartDist = max($horizDist, 28.0);
			$this->landingStartY = max($this->location->y, self::FLY_HEIGHT);
		}

		$portalTopY = $perch->y;

		if($horizDist < 2.5 && abs($this->location->y - $portalTopY) < 3.0){
			$this->beginPerching();
			return;
		}

		$this->setPhase($horizDist > 10.0 ? self::PHASE_LANDING_APPROACH : self::PHASE_LANDING);
		$this->flyGlideTowardsPortal($perch, $portalTopY, $horizDist, $dx, $dz, $tickDiff);
		$this->tryFlapWings();
	}

	private function flyGlideTowardsPortal(Vector3 $portal, float $portalTopY, float $horizDist, float $dx, float $dz, int $tickDiff) : void{
		if($horizDist < 0.15){
			$this->maintainPortalPosition();
			return;
		}

		$toPortalX = -$dx / $horizDist;
		$toPortalZ = -$dz / $horizDist;

		$distFactor = min(1.0, $horizDist / max(1.0, $this->landingStartDist));
		$hSpeed = (0.14 + 0.20 * $distFactor) * max(1, $tickDiff);
		$hStep = min($hSpeed, $horizDist);

		$newHorizDist = max(0.0, $horizDist - $hStep);
		$newY = $this->computeGlideSlopeY($newHorizDist, $portalTopY);

		$newX = $portal->x + ($dx / $horizDist) * $newHorizDist;
		$newZ = $portal->z + ($dz / $horizDist) * $newHorizDist;

		$currentY = $this->location->y;
		$yBlend = min(1.0, 0.14 * max(1, $tickDiff));
		$blendedY = $currentY + ($newY - $currentY) * $yBlend;

		$newPos = $this->avoidObsidianPillars(new Vector3($newX, $blendedY, $newZ));

		$targetYaw = rad2deg(atan2(-$toPortalX, $toPortalZ)) + self::YAW_OFFSET;
		$yAhead = $this->computeGlideSlopeY(max(0.0, $newHorizDist - 2.0), $portalTopY);
		$slopeDrop = $blendedY - $yAhead;
		$targetPitch = max(-58.0, min(12.0, rad2deg(-atan2($slopeDrop, 2.5))));

		$yawLerp = 0.04 + 0.03 * (1.0 - $distFactor);
		$pitchLerp = 0.06 + 0.04 * (1.0 - $distFactor);

		$this->smoothYaw = $this->lerpAngle($this->smoothYaw, $targetYaw, $yawLerp);
		$this->smoothPitch = $this->smoothPitch + ($targetPitch - $this->smoothPitch) * $pitchLerp;
		$this->applyFlightStep($newPos, $this->smoothYaw, $this->smoothPitch);
	}

	private function computeGlideSlopeY(float $horizDist, float $portalTopY) : float{
		$t = min(1.0, max(0.0, $horizDist / max(1.0, $this->landingStartDist)));
		$smooth = $t * $t * (3.0 - 2.0 * $t);
		return $portalTopY + $smooth * ($this->landingStartY - $portalTopY);
	}

	private function beginPerching() : void{
		$this->setPhase(self::PHASE_SITTING_FLAMING);
		$this->perchTimer = 0;
		$this->breathAttackCount = 0;
		$this->breathCooldown = 0;
		$this->sittingDamageReceived = 0.0;
		$this->maintainPortalPosition();
	}

	private function tickPerching(int $tickDiff) : void{
		$this->setPhase(self::PHASE_SITTING_FLAMING);
		$this->maintainPortalPosition();

		$prevTimer = $this->perchTimer;
		$this->perchTimer += $tickDiff;

		$cycleIndex = intdiv($this->perchTimer, self::BREATH_CYCLE_TICKS);
		$cycleTick = $this->perchTimer % self::BREATH_CYCLE_TICKS;

		if($cycleIndex < self::MAX_BREATH_CYCLES){
			$this->updateCrystalBeams(true);

			if(intdiv($this->perchTimer, self::CRYSTAL_HEAL_INTERVAL) > intdiv($prevTimer, self::CRYSTAL_HEAL_INTERVAL)){
				$this->healFromCrystalsOnce();
			}

			if($cycleTick >= self::ROAR_TICKS){
				$flameTick = $cycleTick - self::ROAR_TICKS;
				if($flameTick < self::FLAME_ACTIVE_TICKS){
					for($t = $prevTimer; $t < $this->perchTimer; ++$t){
						$ft = ($t % self::BREATH_CYCLE_TICKS) - self::ROAR_TICKS;
						if($ft >= 0 && $ft < self::FLAME_ACTIVE_TICKS && $ft % 3 === 0){
							$this->spawnDragonBreathBurst();
						}
					}
				}elseif($cycleTick % 12 === 0 && $cycleTick < self::ROAR_TICKS + 80){
					$this->spawnDragonBreathBurst();
				}
			}
		}

		if($this->perchTimer >= self::MAX_BREATH_CYCLES * self::BREATH_CYCLE_TICKS){
			$this->beginTakeoff();
		}elseif($this->sittingDamageReceived >= self::PERCH_DAMAGE_ESCAPE){
			$this->beginTakeoff();
		}
	}

	private function beginTakeoff() : void{
		$this->flightStage = 0;
		$this->clearCrystalBeams();
		$this->setPhase(self::PHASE_TAKEOFF);
	}

	private function tickTakeoff(int $tickDiff) : void{
		$this->setPhase(self::PHASE_TAKEOFF);
		$portal = $this->getPerchPosition();
		$stages = [
			["y" => 4.0, "speed" => 0.10, "lerp" => 0.03, "reach" => 2.5],
			["y" => 12.0, "speed" => 0.12, "lerp" => 0.04, "reach" => 4.0],
			["y" => 24.0, "speed" => 0.16, "lerp" => 0.05, "reach" => 5.5],
			["y" => 40.0, "speed" => 0.20, "lerp" => 0.06, "reach" => 7.0],
		];

		if($this->flightStage < count($stages)){
			$stage = $stages[$this->flightStage];
			$target = $portal->add(0, $stage["y"], 0);
			$this->flyTowards($target, $stage["speed"], $tickDiff, $stage["lerp"]);
			$this->tryFlapWings();
			if($this->location->distanceSquared($target) <= $stage["reach"] * $stage["reach"]){
				++$this->flightStage;
			}
			return;
		}

		$radius = $this->getOrbitRadius();
		$orbitTarget = new Vector3(
			cos($this->circleAngle) * $radius,
			self::FLY_HEIGHT,
			sin($this->circleAngle) * $radius
		);
		$this->flyTowards($orbitTarget, 0.35, $tickDiff, 0.07);
		$this->tryFlapWings();

		if($this->location->distanceSquared($orbitTarget) < 100){
			$this->setPhase(self::PHASE_CIRCLING);
			$this->aiTimer = 0;
			$this->flightStage = 0;
		}
	}

	private function tickCharging(int $tickDiff) : void{
		$this->setPhase(self::PHASE_CHARGING);
		$target = $this->getTargetEntity();
		if(!$target instanceof Player || !$target->isAlive() || $target->isCreative() || $target->isSpectator()){
			$this->setPhase(self::PHASE_CIRCLING);
			return;
		}

		$pos = $target->getPosition()->add(0, 2, 0);
		$this->flyTowards($pos, self::FLY_SPEED * 1.3, $tickDiff);
		$this->tryFlapWings();

		if($this->location->distanceSquared($pos) < 16){
			$target->attack(new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 6));
			$this->setPhase(self::PHASE_CIRCLING);
		}
	}

	private function tryAggroPlayer() : void{
		foreach($this->getWorld()->getPlayers() as $player){
			if(!$player->isAlive() || $player->isCreative() || $player->isSpectator()){
				continue;
			}
			$distSq = $this->location->distanceSquared($player->getPosition());
			if($distSq < 3600 && $distSq > 36 && mt_rand(0, 100) < 15){
				$this->setTargetEntity($player);
				$this->setPhase(self::PHASE_CHARGING);
				return;
			}
		}
	}

	private function getPortalPosition() : Vector3{
		$portalType = VanillaBlocks::END_PORTAL()->getTypeId();
		$bedrockType = VanillaBlocks::BEDROCK()->getTypeId();
		for($y = 101; $y >= 40; --$y){
			if(!$this->getWorld()->isInWorld(self::PORTAL_X, $y, self::PORTAL_Z)){
				continue;
			}
			$id = $this->getWorld()->getBlockAt(self::PORTAL_X, $y, self::PORTAL_Z)->getTypeId();
			if($id === $portalType || $id === $bedrockType){
				return new Vector3(self::PORTAL_X + 0.5, $y, self::PORTAL_Z + 0.5);
			}
		}
		$chunk = $this->getWorld()->getChunk(0, 0);
		$h = $chunk?->getHighestBlockAt(0, 0) ?? 64;
		return new Vector3(self::PORTAL_X + 0.5, $h, self::PORTAL_Z + 0.5);
	}

	private function maintainPortalPosition() : void{
		$portal = $this->getPerchPosition();
		$this->setPositionAndRotation($portal, $this->location->yaw, 0);
		$this->motion = Vector3::zero();
	}

	private function getPerchPosition() : Vector3{
		return $this->getPortalPosition()->add(0, self::PERCH_Y_OFFSET, 0);
	}

	/** @return array<int, array{centerX: int, centerZ: int, radius: int, height: int, guarded: bool}> */
	private function getObsidianPillars() : array{
		return $this->obsidianPillars ??= TheEndGenerator::getObsidianPillars($this->getWorld()->getSeed());
	}

	private function getPillarTopY(array $pillar) : float{
		return $pillar["height"] + ($pillar["guarded"] ? 4 : 1);
	}

	private function elevateTargetAbovePillars(Vector3 $target) : Vector3{
		$minY = $target->y;
		foreach($this->getObsidianPillars() as $pillar){
			$dx = $target->x - $pillar["centerX"];
			$dz = $target->z - $pillar["centerZ"];
			$horizDist = sqrt(($dx * $dx) + ($dz * $dz));
			$avoidRadius = $pillar["radius"] + self::PILLAR_CLEARANCE_H;
			if($horizDist < $avoidRadius){
				$minY = max($minY, $this->getPillarTopY($pillar) + self::PILLAR_CLEARANCE_V);
			}
		}

		return $minY > $target->y ? new Vector3($target->x, $minY, $target->z) : $target;
	}

	private function avoidObsidianPillars(Vector3 $pos) : Vector3{
		$x = $pos->x;
		$y = $pos->y;
		$z = $pos->z;

		foreach($this->getObsidianPillars() as $pillar){
			$cx = $pillar["centerX"];
			$cz = $pillar["centerZ"];
			$dx = $x - $cx;
			$dz = $z - $cz;
			$dist = sqrt(($dx * $dx) + ($dz * $dz));
			$avoidHoriz = $pillar["radius"] + self::PILLAR_CLEARANCE_H;
			$pillarTop = $this->getPillarTopY($pillar);

			if($dist < 0.01){
				$dx = 1.0;
				$dz = 0.0;
				$dist = 1.0;
			}

			if($dist < $avoidHoriz + 2.0 && $y < $pillarTop + self::PILLAR_CLEARANCE_V){
				$y = max($y, $pillarTop + self::PILLAR_CLEARANCE_V);
			}

			if($dist < $avoidHoriz && $y < $pillarTop + self::PILLAR_CLEARANCE_V + 6.0){
				$push = ($avoidHoriz - $dist) + 0.75;
				$x += ($dx / $dist) * $push;
				$z += ($dz / $dist) * $push;
			}
		}

		return new Vector3($x, $y, $z);
	}

	private function countAliveCrystals() : int{
		$tick = $this->getWorld()->getServer()->getTick();
		if($tick - $this->crystalCountCacheTick < self::CRYSTAL_COUNT_CACHE_TICKS){
			return $this->cachedCrystalCount;
		}

		$count = 0;
		foreach($this->getWorld()->getEntities() as $entity){
			if($entity instanceof EndCrystal && $entity->isAlive()){
				++$count;
			}
		}

		$this->cachedCrystalCount = $count;
		$this->crystalCountCacheTick = $tick;
		return $count;
	}

	private function flyTowards(Vector3 $target, float $speed, int $tickDiff = 1, float $turnLerp = 0.12) : void{
		$target = $this->elevateTargetAbovePillars($target);
		$dx = $target->x - $this->location->x;
		$dy = $target->y - $this->location->y;
		$dz = $target->z - $this->location->z;
		$dist = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
		if($dist < 0.2){
			return;
		}

		$step = min($speed * max(1, $tickDiff), $dist);
		$nx = $dx / $dist;
		$ny = $dy / $dist;
		$nz = $dz / $dist;
		$newPos = $this->avoidObsidianPillars($this->location->add($nx * $step, $ny * $step, $nz * $step));

		$horizontal = sqrt($dx * $dx + $dz * $dz);
		$targetYaw = rad2deg(atan2(-$dx, $dz)) + self::YAW_OFFSET;
		$targetPitch = $horizontal > 0.01 ? rad2deg(-atan2($dy, $horizontal)) : 0.0;
		$targetPitch = max(-40.0, min(40.0, $targetPitch));

		$this->smoothYaw = $this->lerpAngle($this->smoothYaw, $targetYaw, $turnLerp);
		$this->smoothPitch = $this->smoothPitch + ($targetPitch - $this->smoothPitch) * $turnLerp;
		$this->applyFlightStep($newPos, $this->smoothYaw, $this->smoothPitch);
	}

	private function applyFlightStep(Vector3 $newPos, float $yaw, float $pitch) : void{
		$this->setRotation($yaw, $pitch);
		$this->motion = new Vector3(
			$newPos->x - $this->location->x,
			$newPos->y - $this->location->y,
			$newPos->z - $this->location->z
		);
		$this->setForceMovementUpdate(true);
	}

	private function lerpAngle(float $from, float $to, float $t) : float{
		$diff = $to - $from + 540.0;
		$wrapped = $diff - 360.0 * floor($diff / 360.0);
		$delta = $wrapped - 180.0;
		return $from + $delta * $t;
	}

	private function tryFlapWings() : void{
		if($this->flapCooldown > 0){
			return;
		}
		$this->flapCooldown = 12;
		$this->broadcastSound(new EnderDragonFlapSound($this));
	}

	private function spawnDragonBreathBurst() : void{
		$count = max(1, (int) round((2 + mt_rand(0, 1)) / 1.5));
		for($i = 0; $i < $count; ++$i){
			$this->spawnDragonBreathOnSurface();
		}
	}

	private function spawnDragonBreathOnSurface() : void{
		$portal = $this->getPortalPosition();
		$world = $this->getWorld();
		$angle = (mt_rand() / mt_getrandmax()) * 2 * M_PI;
		$radius = 3.0 + (mt_rand() / mt_getrandmax()) * 5.0;
		$surfaceX = $portal->x + cos($angle) * $radius;
		$surfaceZ = $portal->z + sin($angle) * $radius;
		$surfaceY = $this->findIslandSurfaceY((int) floor($surfaceX), (int) floor($surfaceZ));

		$world->addParticle(new Vector3($surfaceX, $surfaceY + 0.05, $surfaceZ), new DragonBreathParticle());

		$cloud = new AreaEffectCloud(Location::fromObject(new Vector3($surfaceX, $surfaceY, $surfaceZ), $world, 0, 0));
		$cloud->configureAsDragonBreath(self::SMOKE_RADIUS, self::SMOKE_DURATION);
		$cloud->spawnToAll();
	}

	private function findIslandSurfaceY(int $x, int $z) : int{
		$world = $this->getWorld();
		$portalY = (int) $this->getPortalPosition()->y;
		for($y = min(101, $portalY + 10); $y >= max(40, $portalY - 15); --$y){
			if(!$world->isInWorld($x, $y, $z)){
				continue;
			}
			$block = $world->getBlockAt($x, $y, $z);
			if(!$block->isSolid() || $block->canBeReplaced()){
				continue;
			}
			$above = $world->getBlockAt($x, $y + 1, $z);
			if($above->canBeReplaced()){
				return $y + 1;
			}
		}
		return $portalY;
	}

	private function healFromCrystalsOnce() : void{
		if($this->getHealth() >= $this->getMaxHealth()){
			return;
		}

		if($this->findNearestAliveCrystal() === null){
			return;
		}

		$this->heal(new EntityRegainHealthEvent($this, self::CRYSTAL_HEAL_AMOUNT, EntityRegainHealthEvent::CAUSE_MAGIC));
	}

	private function findNearestAliveCrystal() : ?EndCrystal{
		$nearest = null;
		$nearestDistSq = 64.0 * 64.0;
		foreach($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(64, 64, 64)) as $entity){
			if(!$entity instanceof EndCrystal || !$entity->isAlive()){
				continue;
			}
			$distSq = $entity->getPosition()->distanceSquared($this->getPosition());
			if($distSq <= $nearestDistSq){
				$nearest = $entity;
				$nearestDistSq = $distSq;
			}
		}
		return $nearest;
	}

	private function updateCrystalBeams(bool $active) : void{
		$healingCrystal = $active ? $this->findNearestAliveCrystal() : null;
		foreach($this->getWorld()->getNearbyEntities($this->getBoundingBox()->expandedCopy(64, 64, 64)) as $entity){
			if(!$entity instanceof EndCrystal){
				continue;
			}
			$entity->setBeamTarget(
				$active && $entity->isAlive() && $healingCrystal !== null && $entity->getId() === $healingCrystal->getId()
					? $this->getPosition()
					: null
			);
		}
	}

	private function clearCrystalBeams() : void{
		$this->updateCrystalBeams(false);
	}

	protected function doHitAnimation() : void{
		$this->broadcastAnimation(new HurtAnimation($this));
	}

	protected function onDeath() : void{
		$ev = new EntityDeathEvent($this, $this->getDrops(), $this->getXpDropAmount());
		$ev->call();
		foreach($ev->getDrops() as $item){
			$this->getWorld()->dropItem($this->location, $item);
		}
		$this->getWorld()->dropExperience($this->location, $ev->getXpDropAmount());

		$this->setPhase(self::PHASE_DYING);
		$this->deathAnimationPlayed = false;
		$this->maxDeadTicks = 320;
		$this->deadTicks = 0;
	}

	protected function onDeathUpdate(int $tickDiff) : bool{
		if(!$this->deathAnimationPlayed){
			$portal = $this->getPortalPosition();
			if($this->location->distanceSquared($portal) > 9){
				$this->flyTowards($portal, 0.5, max(1, $tickDiff));
				$this->tryFlapWings();
				return false;
			}

			$this->maintainPortalPosition();
			$this->motion = Vector3::zero();
			$this->broadcastAnimation(new EnderDragonDeathAnimation($this));
			$this->broadcastSound(new EnderDragonDeathSound($this));
			$this->getWorld()->addSound($portal, new ExplodeSound());
			$this->getWorld()->addSound($portal, new EndPortalSpawnSound());
			$this->deathAnimationPlayed = true;
			$this->hideBossBarForAll();
			return false;
		}

		if($this->deadTicks < $this->maxDeadTicks){
			$this->deadTicks += $tickDiff;
			if($this->deadTicks >= $this->maxDeadTicks){
				$this->endDeathAnimation();
			}
		}

		return $this->deadTicks >= $this->maxDeadTicks;
	}

	protected function endDeathAnimation() : void{
		$this->despawnFromAll();
	}

	public function getDrops() : array{
		return [];
	}

	public function getXpDropAmount() : int{
		return 500;
	}

	public function getPickedItem() : ?Item{
		return null;
	}
}
