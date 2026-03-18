<?php

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\particle\EndermanTeleportParticle;
use pocketmine\world\sound\EndermanScreamSound;
use pocketmine\world\sound\EndermanStareSound;
use pocketmine\world\sound\EndermanTeleportSound;
use function mt_rand;
use function sqrt;
use function atan2;
use function cos;
use function sin;
use function abs;

class Enderman extends Living{

	public static function getNetworkTypeId() : string{ return EntityIds::ENDERMAN; }
	
	private int $moveTimer = 0;
	private ?Vector3 $moveTarget = null;
	private int $idleTimer = 0;
	private int $teleportTimer = 0;
	
	private bool $isAngry = false;
	private ?Player $targetPlayer = null;
	private int $attackCooldown = 0;
	private int $stareTimer = 0;
	
	private bool $isStaredAt = false;
	private int $screamTimer = 0;

	private bool $isJumping = false;
	private int $jumpTimer = 0;

	/** @var int[]|null state IDs блоков, которые эндермен может нести */
	private static ?array $carryableStateIds = null;
	/** stateId блока в руках, 0 — ничего не несёт */
	private int $carriedBlockStateId = 0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(2.9, 0.6);
	}

	public function getName() : string{
		return "Enderman";
	}

	public function getDrops() : array{
		return [
			VanillaItems::ENDER_PEARL()->setCount(mt_rand(0, 1))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaItems::ENDERMAN_SPAWN_EGG();
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		if($nbt->getTag("CarriedBlockStateId") !== null){
			$this->carriedBlockStateId = $nbt->getInt("CarriedBlockStateId", 0);
		}elseif(mt_rand(1, 100) <= 35){
			$ids = self::getCarryableStateIds();
			if($ids !== []){
				$this->carriedBlockStateId = $ids[array_rand($ids)];
			}
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		if($this->carriedBlockStateId !== 0){
			$nbt->setInt("CarriedBlockStateId", $this->carriedBlockStateId);
		}
		return $nbt;
	}

	/** @return int[] state IDs блоков, которые эндермен может брать в руки */
	private static function getCarryableStateIds() : array{
		if(self::$carryableStateIds === null){
			$blocks = [
				VanillaBlocks::GRASS(),
				VanillaBlocks::DIRT(),
				VanillaBlocks::SAND(),
				VanillaBlocks::GRAVEL(),
				VanillaBlocks::STONE(),
				VanillaBlocks::COBBLESTONE(),
				VanillaBlocks::OAK_PLANKS(),
				VanillaBlocks::BROWN_MUSHROOM_BLOCK(),
				VanillaBlocks::RED_MUSHROOM_BLOCK(),
				VanillaBlocks::TNT(),
				VanillaBlocks::CACTUS(),
				VanillaBlocks::CLAY(),
				VanillaBlocks::PUMPKIN(),
				VanillaBlocks::MELON(),
				VanillaBlocks::CRIMSON_NYLIUM(),
				VanillaBlocks::WARPED_NYLIUM(),
			];
			self::$carryableStateIds = array_map(fn(Block $b) => $b->getStateId(), $blocks);
		}
		return self::$carryableStateIds;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::ANGRY, $this->isAngry);
		$networkId = $this->carriedBlockStateId !== 0
			? TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($this->carriedBlockStateId)
			: 0;
		$properties->setInt(EntityMetadataProperties::ENDERMAN_HELD_ITEM_ID, $networkId);
	}

	public function onUpdate(int $currentTick) : bool{
		$hasUpdate = parent::onUpdate($currentTick);
		
		if($this->teleportTimer > 0){
			$this->teleportTimer--;
		}
		if($this->attackCooldown > 0){
			$this->attackCooldown--;
		}
		if($this->screamTimer > 0){
			$this->screamTimer--;
		}
		
		$this->checkPlayerStaring();
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

	private function checkPlayerStaring() : void{
		$wasStaredAt = $this->isStaredAt;
		$this->isStaredAt = false;
		
		foreach($this->getWorld()->getPlayers() as $player){
			if($this->location->distance($player->getLocation()) > 64) continue;
			
			if($this->isPlayerLookingAtMe($player)){
				$this->isStaredAt = true;
				
				if(!$wasStaredAt && !$this->isAngry){
					$this->stareTimer++;
					if($this->stareTimer > 5){
						$this->becomeAngry($player);
						$this->getWorld()->addSound($this->location, new EndermanStareSound());
					}
				}
				break;
			}
		}
		
		if(!$this->isStaredAt){
			$this->stareTimer = 0;
		}
	}

	private function isPlayerLookingAtMe(Player $player) : bool{
		$playerPos = $player->getEyePos();
		$endermanPos = $this->location->add(0, 2.5, 0);
		
		$dx = $endermanPos->x - $playerPos->x;
		$dy = $endermanPos->y - $playerPos->y;
		$dz = $endermanPos->z - $playerPos->z;
		
		$distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
		if($distance > 64) return false;
		
		$dx /= $distance;
		$dy /= $distance;
		$dz /= $distance;
		
		$playerYaw = $player->getLocation()->yaw;
		$playerPitch = $player->getLocation()->pitch;
		
		$lookX = -sin($playerYaw * M_PI / 180) * cos($playerPitch * M_PI / 180);
		$lookY = -sin($playerPitch * M_PI / 180);
		$lookZ = cos($playerYaw * M_PI / 180) * cos($playerPitch * M_PI / 180);
		
		$dot = $lookX * $dx + $lookY * $dy + $lookZ * $dz;
		
		return $dot > 0.975;
	}

	private function becomeAngry(?Player $player) : void{
		if($this->isAngry) return;
		
		$this->isAngry = true;
		$this->targetPlayer = $player;
		$this->screamTimer = 400;
		$this->networkPropertiesDirty = true;
		
		if($this->screamTimer > 0){
			$this->getWorld()->addSound($this->location, new EndermanScreamSound());
		}
	}

	private function updateAI() : void{
		if($this->isAngry && $this->targetPlayer !== null){
			$this->updateAngryBehavior();
		}else{
			$this->updatePassiveBehavior();
		}
	}

	private function updateAngryBehavior() : void{
		if($this->targetPlayer === null || !$this->targetPlayer->isAlive() || $this->targetPlayer->isClosed()){
			$this->isAngry = false;
			$this->targetPlayer = null;
			$this->networkPropertiesDirty = true;
			return;
		}
		
		$distance = $this->location->distance($this->targetPlayer->getLocation());
		
		if($distance > 64){
			$this->isAngry = false;
			$this->targetPlayer = null;
			$this->networkPropertiesDirty = true;
			return;
		}
		
		if($distance > 3 && mt_rand(0, 50) === 0 && $this->teleportTimer <= 0){
			$this->teleportNearTarget($this->targetPlayer->getLocation());
		}
		
		if($distance < 2.5 && $this->attackCooldown <= 0){
			$this->attackTarget();
		}else{
			$this->moveTowards($this->targetPlayer->getLocation(), 0.25);
		}
	}

	private function updatePassiveBehavior() : void{
		if(mt_rand(0, 200) === 0 && $this->teleportTimer <= 0){
			$this->randomTeleport();
		}
		
		$this->moveTimer--;
		
		if($this->moveTimer <= 0){
			if($this->idleTimer > 0){
				$this->idleTimer--;
				$this->moveTarget = null;
				return;
			}
			
			if(mt_rand(0, 10) < 7){
				$this->idleTimer = mt_rand(40, 100);
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
				$this->moveTowards($this->moveTarget, 0.15);
			}
		}
	}

	private function selectNewWanderTarget() : void{
		for($i = 0; $i < 10; $i++){
			$angle = mt_rand(0, 360) * M_PI / 180;
			$distance = mt_rand(5, 15);
			
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
		
		for($y = $currentY + 3; $y >= $currentY - 5; $y--){
			$block = $world->getBlockAt($x, $y, $z);
			$blockAbove = $world->getBlockAt($x, $y + 1, $z);
			$blockAbove2 = $world->getBlockAt($x, $y + 2, $z);
			$blockAbove3 = $world->getBlockAt($x, $y + 3, $z);
			
			if($block->isSolid() && !$blockAbove->isSolid() && !$blockAbove2->isSolid() && !$blockAbove3->isSolid()){
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
		$currentY = (int) $this->location->y;
		$checkX = (int) round($nextX);
		$checkZ = (int) round($nextZ);
		
		$blockAtFeet = $world->getBlockAt($checkX, $currentY, $checkZ);
		$blockAboveFeet = $world->getBlockAt($checkX, $currentY + 1, $checkZ);
		
		if($blockAtFeet->isSolid() && !$blockAboveFeet->isSolid()){
			return true;
		}
		
		$blockAboveGround = $world->getBlockAt($checkX, $currentY + 1, $checkZ);
		if($blockAboveGround->isSolid()){
			$blockAtHead = $world->getBlockAt($checkX, $currentY + 2, $checkZ);
			if(!$blockAtHead->isSolid()){
				return true;
			}
			// как корова/свинья — подъём на блок выше (препятствие в 2 блока)
			$blockAboveHead = $world->getBlockAt($checkX, $currentY + 3, $checkZ);
			if(!$blockAboveHead->isSolid()){
				return true;
			}
		}
		
		return false;
	}

	private function tryJump() : void{
		if($this->onGround && !$this->isJumping){
			$this->isJumping = true;
			$this->jumpTimer = 10;
			// чуть сильнее прыжок, чтобы забираться на блок выше (как корова/свинья)
			$this->motion = new Vector3($this->motion->x, 0.6, $this->motion->z);
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
		$this->moveTarget = null;
	}

	private function canMoveTo(float $x, float $z) : bool{
		$world = $this->getWorld();
		$currentY = (int) $this->location->y;
		
		for($y = $currentY; $y <= $currentY + 2; $y++){
			if($world->getBlockAt((int) $x, $y, (int) $z)->isSolid()){
				return false;
			}
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
		
		$maxTurn = 20;
		if(abs($diff) > $maxTurn) $diff = ($diff > 0) ? $maxTurn : -$maxTurn;
		
		$this->setRotation($currentYaw + $diff, $this->location->pitch);
	}

	private function attackTarget() : void{
		if($this->targetPlayer === null) return;
		
		$ev = new EntityDamageByEntityEvent($this, $this->targetPlayer, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 7);
		$this->targetPlayer->attack($ev);
		
		$this->broadcastAnimation(new ArmSwingAnimation($this));
		$this->attackCooldown = 20;
		
		if(mt_rand(0, 3) === 0){
			$this->randomTeleport();
		}
	}

	private function randomTeleport() : void{
		$world = $this->getWorld();
		
		for($i = 0; $i < 16; $i++){
			$x = $this->location->x + mt_rand(-32, 32);
			$y = $this->location->y + mt_rand(-16, 16);
			$z = $this->location->z + mt_rand(-32, 32);
			
			$targetY = $this->findSafeY((int)$x, (int)$z);
			if($targetY !== null){
				$this->teleportTo(new Vector3($x, $targetY, $z));
				return;
			}
		}
	}

	private function teleportNearTarget(Vector3 $target) : void{
		$world = $this->getWorld();
		
		for($i = 0; $i < 16; $i++){
			$angle = mt_rand(0, 360) * M_PI / 180;
			$distance = mt_rand(2, 8);
			
			$x = $target->x + cos($angle) * $distance;
			$z = $target->z + sin($angle) * $distance;
			
			$targetY = $this->findSafeY((int)$x, (int)$z);
			if($targetY !== null){
				$this->teleportTo(new Vector3($x, $targetY, $z));
				return;
			}
		}
	}

	private function teleportTo(Vector3 $pos) : void{
		$oldPos = $this->location->asVector3();
		
		$this->getWorld()->addParticle($oldPos, new EndermanTeleportParticle());
		$this->getWorld()->addSound($oldPos, new EndermanTeleportSound());
		
		$this->teleport($pos);
		
		$this->getWorld()->addParticle($pos, new EndermanTeleportParticle());
		$this->getWorld()->addSound($pos, new EndermanTeleportSound());
		
		$this->teleportTimer = 20;
	}

	public function attack(EntityDamageEvent $source) : void{
		if($source instanceof EntityDamageByEntityEvent){
			$damager = $source->getDamager();
			if($damager instanceof Player && !$damager->isCreative()){
				$this->becomeAngry($damager);
			}
		}
		
		if(mt_rand(0, 10) < 8 && $this->teleportTimer <= 0){
			$this->randomTeleport();
			$source->cancel();
			return;
		}
		
		parent::attack($source);
	}
}
