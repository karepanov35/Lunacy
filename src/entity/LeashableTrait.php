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

namespace pocketmine\entity;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;
use function atan2;
use function deg2rad;
use function sin;
use function sqrt;
use const M_PI;

trait LeashableTrait{

	private ?string $leashHolderUuid = null;

	protected function initLeashFromNBT(CompoundTag $nbt) : void{
		$lu = $nbt->getString(Leashable::LEASH_TAG_UUID, "");
		$this->leashHolderUuid = $lu !== "" ? $lu : null;
	}

	protected function saveLeashToNBT(CompoundTag $nbt) : void{
		if($this->leashHolderUuid !== null){
			$nbt->setString(Leashable::LEASH_TAG_UUID, $this->leashHolderUuid);
		}
	}

	protected function syncLeashNetworkData(EntityMetadataCollection $properties) : void{
		$leadHolderEid = -1;
		if($this->leashHolderUuid !== null){
			$lh = $this->resolveLeashHolder();
			if($lh !== null && $lh->getWorld() === $this->getWorld()){
				$leadHolderEid = $lh->getId();
			}
		}
		$properties->setLong(EntityMetadataProperties::LEAD_HOLDER_EID, $leadHolderEid);
	}

	public function isLeashed() : bool{
		return $this->leashHolderUuid !== null;
	}

	public function toggleLeashWithLead(Player $player) : int{
		$uuid = $player->getUniqueId()->toString();
		if($this->leashHolderUuid !== null){
			if($this->leashHolderUuid !== $uuid){
				return Leashable::LEASH_INTERACT_NONE;
			}
			$this->leashHolderUuid = null;
			$this->broadcastLeashMetadata();

			return Leashable::LEASH_INTERACT_DETACHED;
		}
		$this->leashHolderUuid = $uuid;
		$this->broadcastLeashMetadata();

		return Leashable::LEASH_INTERACT_ATTACHED;
	}

	/**
	 * @return true when normal AI should be skipped this tick
	 */
	protected function tickLeash(int $tickDiff) : bool{
		if($this->leashHolderUuid === null){
			return false;
		}

		$leashHolder = $this->resolveLeashHolder();
		if($leashHolder === null || $leashHolder->getWorld() !== $this->getWorld()){
			$this->breakLeash(true);

			return false;
		}

		if($this->location->distanceSquared($leashHolder->getPosition()) > Leashable::LEASH_MAX_DISTANCE_SQ){
			$this->breakLeash(true);

			return false;
		}

		if($this->shouldFollowLeashHolder()){
			$this->tickLeashFollow($leashHolder);
			$this->onLeashTick($tickDiff);
		}

		return $this->shouldSkipAITWhileLeashed();
	}

	protected function shouldFollowLeashHolder() : bool{
		return true;
	}

	protected function shouldSkipAITWhileLeashed() : bool{
		return true;
	}

	protected function onLeashTick(int $tickDiff) : void{
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

	private function breakLeash(bool $dropLeadItem) : void{
		if($dropLeadItem){
			$this->getWorld()->dropItem($this->location->add(0, 0.4, 0), VanillaItems::LEAD());
		}
		$this->leashHolderUuid = null;
		$this->broadcastLeashMetadata();
	}

	private function broadcastLeashMetadata() : void{
		$props = $this->getNetworkProperties();
		$leadHolderEid = -1;
		if($this->leashHolderUuid !== null){
			$lh = $this->resolveLeashHolder();
			if($lh !== null && $lh->getWorld() === $this->getWorld()){
				$leadHolderEid = $lh->getId();
			}
		}
		$props->setLong(EntityMetadataProperties::LEAD_HOLDER_EID, $leadHolderEid);
		$dirty = $props->getDirty();
		if(count($dirty) > 0){
			$this->sendData(null, $dirty);
			$props->clearDirtyProperties();
		}
	}

	private function tickLeashFollow(Player $holder) : void{
		$holderPos = $holder->getPosition();
		$dxToHolder = $holderPos->x - $this->location->x;
		$dzToHolder = $holderPos->z - $this->location->z;
		$distToHolder = sqrt($dxToHolder * $dxToHolder + $dzToHolder * $dzToHolder);

		if($distToHolder > 8.0){
			$yaw = $holder->getLocation()->yaw;
			$rad = deg2rad($yaw);
			$backX = sin($rad);
			$backZ = -cos($rad);
			$want = new Vector3(
				$holderPos->x + $backX * Leashable::LEASH_FOLLOW_OFFSET,
				$holderPos->y,
				$holderPos->z + $backZ * Leashable::LEASH_FOLLOW_OFFSET
			);
			$this->teleport($want);
			$this->motion = Vector3::zero();
			$this->lookAtLeashHolder($holder);

			return;
		}

		if($distToHolder < Leashable::LEASH_MIN_DISTANCE){
			$this->motion->x *= 0.2;
			$this->motion->z *= 0.2;

			return;
		}

		$yaw = $holder->getLocation()->yaw;
		$rad = deg2rad($yaw);
		$backX = sin($rad);
		$backZ = -cos($rad);
		$want = new Vector3(
			$holderPos->x + $backX * Leashable::LEASH_FOLLOW_OFFSET,
			$holderPos->y,
			$holderPos->z + $backZ * Leashable::LEASH_FOLLOW_OFFSET
		);

		$dx = $want->x - $this->location->x;
		$dz = $want->z - $this->location->z;
		$dist = sqrt($dx * $dx + $dz * $dz);
		if($dist < 0.5){
			$this->motion->x *= 0.2;
			$this->motion->z *= 0.2;

			return;
		}
		$speed = min(0.35, $dist * 0.12);
		$this->motion->x = ($dx / $dist) * $speed;
		$this->motion->z = ($dz / $dist) * $speed;
		$this->lookAtLeashHolder($holder);
	}

	private function lookAtLeashHolder(Living $target) : void{
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

	/**
	 * @param Item[] $drops
	 * @return Item[]
	 */
	protected function addLeashToDrops(array $drops) : array{
		if($this->leashHolderUuid !== null){
			$drops[] = VanillaItems::LEAD();
			$this->leashHolderUuid = null;
			$this->broadcastLeashMetadata();
		}

		return $drops;
	}
}
