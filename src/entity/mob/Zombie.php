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

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Monster;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function cos;
use function mt_rand;
use function sin;

class Zombie extends Monster{
	private ?Player $target = null;
	private int $idleTime = 0;
	private ?Vector3 $wanderTarget = null;
	private int $fireTickCooldown = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::ZOMBIE;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	public function getName() : string{
		return "Zombie";
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setMaxHealth(20);
		$this->setHealth(20);
		$this->setStepHeight(1.0);
		$this->setCanSaveWithChunk(false);
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		parent::entityBaseTick($tickDiff);
		if(!$this->isAlive()){
			return true;
		}

		$this->checkDaylightBurning();
		$this->validateTarget();

		if($this->target === null){
			$this->findNearestPlayer();
			$this->tickWander();
		}else{
			$this->tickCombat();
		}

		$this->handleVanillaJump(0.5);
		$this->tickAiCooldowns($tickDiff);

		return true;
	}

	private function tickCombat() : void{
		if($this->attackTime > 0){
			$this->lookAt($this->target->getEyePos());
			return;
		}

		$targetPos = $this->target->getPosition();
		$this->lookAt($this->target->getEyePos());

		if($this->location->distanceSquared($targetPos) <= 1.6){
			$this->smoothMotionX *= 0.6;
			$this->smoothMotionZ *= 0.6;
			$this->motion->x = $this->smoothMotionX;
			$this->motion->z = $this->smoothMotionZ;
			$this->attackTarget();
			return;
		}

		$this->moveTowardsPoint($targetPos, 0.22, true, true);
	}

	private function attackTarget() : void{
		if($this->attackCooldown > 0 || $this->target === null){
			return;
		}

		$this->broadcastAnimation(new ArmSwingAnimation($this));
		$this->target->attack(new EntityDamageByEntityEvent($this, $this->target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 3));
		$this->attackCooldown = 20;
	}

	private function tickWander() : void{
		if($this->idleTime > 0){
			$this->idleTime--;
			$this->motion->x = $this->smoothMotionX *= 0.85;
			$this->motion->z = $this->smoothMotionZ *= 0.85;
			return;
		}

		if($this->wanderTarget === null || $this->location->distanceSquared($this->wanderTarget) < 9){
			$this->idleTime = 80;
			$angle = mt_rand(0, 359) * (M_PI / 180);
			$dist = mt_rand(6, 12);
			$this->wanderTarget = $this->location->add(cos($angle) * $dist, 0, sin($angle) * $dist);
		}

		$this->moveTowardsPoint($this->wanderTarget, 0.22, true, true);
	}

	private function checkDaylightBurning() : void{
		if($this->fireTickCooldown > 0){
			$this->fireTickCooldown--;
			return;
		}
		if($this->getWorld()->getTimeOfDay() >= 12000 || $this->isUnderwater()){
			return;
		}
		$highestY = $this->getWorld()->getHighestBlockAt((int) $this->location->x, (int) $this->location->z);
		if($highestY !== null && $this->location->y >= $highestY){
			$this->setOnFire(3);
			$this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_FIRE_TICK, 1));
			$this->fireTickCooldown = 20;
		}
	}

	private function validateTarget() : void{
		if($this->target === null){
			return;
		}
		if(
			!$this->target->isAlive()
			|| $this->target->isClosed()
			|| $this->target->isCreative()
			|| $this->location->distanceSquared($this->target->getPosition()) > 400
		){
			$this->target = null;
		}
	}

	private function findNearestPlayer() : void{
		foreach($this->getWorld()->getPlayers() as $player){
			if(!$player->isAlive() || $player->isCreative()){
				continue;
			}
			if($this->location->distanceSquared($player->getPosition()) < 256){
				$this->target = $player;
				break;
			}
		}
	}

	public function getDrops() : array{
		$drops = [VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(1, 2))];
		if(mt_rand(0, 100) < 5){
			$drops[] = VanillaItems::IRON_INGOT();
		}
		return $drops;
	}

	public function getXpDropAmount() : int{
		return 5;
	}
}
