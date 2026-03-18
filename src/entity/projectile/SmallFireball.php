<?php

declare(strict_types=1);

namespace pocketmine\entity\projectile;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\Living;
use pocketmine\entity\object\EndCrystal;
use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\math\RayTraceResult;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class SmallFireball extends Projectile{

	public static function getNetworkTypeId() : string{
		return EntityIds::SMALL_FIREBALL;
	}

	protected float $damage = 5.0;

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.3125, 0.3125);
	}

	protected function getInitialDragMultiplier() : float{
		return 0.01;
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	public function __construct(Location $location, ?Entity $shootingEntity, ?CompoundTag $nbt = null){
		parent::__construct($location, $shootingEntity, $nbt);
		$this->setOnFire(100);
	}

	public function canCollideWith(Entity $entity) : bool{
		return ($entity instanceof Living || $entity instanceof EndCrystal) && !$this->onGround;
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
		$damage = $this->getResultDamage();
		if($damage >= 0){
			$owner = $this->getOwningEntity();
			if($owner === null){
				$ev = new EntityDamageByEntityEvent($this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
			}else{
				$ev = new EntityDamageByChildEntityEvent($owner, $this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
			}
			$entityHit->attack($ev);

			if($entityHit instanceof Living){
				$fireEv = new EntityCombustByEntityEvent($this, $entityHit, 5);
				$fireEv->call();
				if(!$fireEv->isCancelled()){
					$entityHit->setOnFire($fireEv->getDuration());
				}
			}
		}
		$this->flagForDespawn();
	}

	protected function onHit(ProjectileHitEvent $event) : void{
		$this->flagForDespawn();
	}
}
