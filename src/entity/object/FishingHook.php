<?php

declare(strict_types=1);

namespace pocketmine\entity\object;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\particle\WaterParticle;
use pocketmine\world\sound\FizzSound;
use pocketmine\world\sound\WaterSplashSound;
use function mt_rand;

class FishingHook extends Projectile{

	public static function getNetworkTypeId() : string{ return EntityIds::FISHING_HOOK; }

	private int $ticksCatchable = 0;
	private int $caughtEntity = 0;
	private bool $attracted = false;
	private int $attractTimer = 0;
	private int $attractionTime = 0;
	private bool $hasPlayedSplash = false;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.25, 0.25);
	}

	protected function getInitialDragMultiplier() : float{ return 0.05; }

	protected function getInitialGravity() : float{ return 0.04; }

	public function canCollideWith(Entity $entity) : bool{
		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		$owner = $this->getOwningEntity();
		if($owner === null || $owner->isClosed() || !$owner->isAlive()){
			$this->flagForDespawn();
			return true;
		}

		if(!$owner instanceof Player){
			$this->flagForDespawn();
			return true;
		}

		$heldItem = $owner->getInventory()->getItemInHand();
		if($heldItem->getTypeId() !== VanillaItems::FISHING_ROD()->getTypeId()){
			$this->reelIn();
			return true;
		}

		if($this->location->distanceSquared($owner->getLocation()) > 1024){
			$this->reelIn();
			return true;
		}

		$block = $this->getWorld()->getBlock($this->location);
		$blockBelow = $this->getWorld()->getBlock($this->location->subtract(0, 0.5, 0));
		$isInWater = $block->getTypeId() === BlockTypeIds::WATER || $blockBelow->getTypeId() === BlockTypeIds::WATER;

		if($isInWater){
			if(!$this->hasPlayedSplash){
				$this->getWorld()->addSound($this->location, new WaterSplashSound(1.0));
				$this->hasPlayedSplash = true;
				for($i = 0; $i < 5; $i++){
					$this->getWorld()->addParticle(
						$this->location->add(
							(mt_rand(-5, 5) / 10),
							0.1,
							(mt_rand(-5, 5) / 10)
						),
						new WaterParticle()
					);
				}
			}
			
			$motion = $this->getMotion();
			$this->setMotion(new Vector3($motion->x * 0.8, $motion->y * 0.8, $motion->z * 0.8));
			
			if($motion->y < 0){
				$this->setMotion(new Vector3($motion->x, $motion->y * 0.6, $motion->z));
			}
			
			$blockAbove = $this->getWorld()->getBlock($this->location->add(0, 1, 0));
			if($blockAbove->getTypeId() === BlockTypeIds::WATER){
				$this->setMotion(new Vector3($motion->x, 0.1, $motion->z));
			}else{
				if($motion->y < -0.03){
					$this->setMotion(new Vector3($motion->x, -0.03, $motion->z));
				}
			}
			
			if(!$this->attracted && abs($motion->x) < 0.1 && abs($motion->z) < 0.1){
				if($this->attractTimer === 0){
					$this->attractionTime = mt_rand(100, 600);
				}

				$this->attractTimer++;

				if($this->attractTimer % 20 === 0){
					$this->getWorld()->addParticle($this->location->subtract(0, 0.3, 0), new BubbleParticle());
				}

				if($this->attractTimer >= $this->attractionTime - 40 && $this->attractTimer % 5 === 0){
					$angle = ($this->attractTimer * 0.3);
					$distance = 3 - (($this->attractTimer - ($this->attractionTime - 40)) / 40) * 3;
					$x = \cos($angle) * $distance;
					$z = \sin($angle) * $distance;
					$this->getWorld()->addParticle(
						$this->location->add($x, -0.5, $z),
						new BubbleParticle()
					);
				}

				if($this->attractTimer >= $this->attractionTime){
					$this->attracted = true;
					$this->ticksCatchable = mt_rand(40, 80);
					
					for($i = 0; $i < 15; $i++){
						$this->getWorld()->addParticle(
							$this->location->add(
								(mt_rand(-15, 15) / 10),
								0.1,
								(mt_rand(-15, 15) / 10)
							),
							new WaterParticle()
						);
					}
					
					$this->getWorld()->addSound($this->location, new FizzSound());
					
					$this->setMotion(new Vector3($motion->x, -0.4, $motion->z));
				}
			}
		}elseif(!$isInWater){
			$this->attractTimer = 0;
			$this->attracted = false;
			$this->ticksCatchable = 0;
		}

		if($this->ticksCatchable > 0){
			$this->ticksCatchable--;
			if($this->ticksCatchable === 0){
				$this->attracted = false;
				$this->attractTimer = 0;
			}
		}

		return $hasUpdate;
	}

	public function reelIn() : void{
		$owner = $this->getOwningEntity();
		if($owner instanceof Player && !$this->isFlaggedForDespawn()){
			if($this->attracted && $this->ticksCatchable > 0){
				$this->catchFish($owner);
			}elseif($this->attracted && $this->ticksCatchable === 0){
				$this->attracted = false;
				$this->attractTimer = 0;
			}

			$motion = $owner->getLocation()->subtractVector($this->location)->normalize()->multiply(0.3);
			$this->setMotion($motion);
		}

		$this->flagForDespawn();
	}

	private function catchFish(Player $player) : void{
		$rand = mt_rand(1, 100);
		
		if($rand <= 85){
			$items = [
				VanillaItems::RAW_FISH(),
				VanillaItems::RAW_SALMON(),
				VanillaItems::CLOWNFISH(),
				VanillaItems::PUFFERFISH()
			];
			$item = $items[array_rand($items)];
		}elseif($rand <= 95){
			$items = [
				VanillaItems::BOW(),
				VanillaItems::ENCHANTED_BOOK(),
				VanillaItems::NAME_TAG(),
				VanillaBlocks::LILY_PAD()->asItem()
			];
			$item = $items[array_rand($items)];
		}else{
			$items = [
				VanillaItems::BOWL(),
				VanillaItems::LEATHER(),
				VanillaItems::LEATHER_BOOTS(),
				VanillaItems::ROTTEN_FLESH(),
				VanillaItems::STICK(),
				VanillaItems::STRING()
			];
			$item = $items[array_rand($items)];
		}

		$itemEntity = $player->getWorld()->dropItem($this->location, $item);
		if($itemEntity !== null){
			$direction = $player->getEyePos()->subtractVector($this->location)->normalize();
			$itemEntity->setMotion($direction->multiply(0.6));
		}

		$rod = $player->getInventory()->getItemInHand();
		if($rod->getTypeId() === VanillaItems::FISHING_ROD()->getTypeId()){
			$rod->applyDamage(1);
			$player->getInventory()->setItemInHand($rod);
		}

		$player->getXpManager()->addXp(mt_rand(1, 6));
		
		$player->getWorld()->addSound($player->getLocation(), new \pocketmine\world\sound\XpCollectSound());
	}

	protected function onHit(ProjectileHitEvent $event) : void{
	}

	protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult) : void{
		$this->setMotion(new Vector3(0, 0, 0));
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
	}

	protected function despawnsOnEntityHit() : bool{
		return false;
	}
}

