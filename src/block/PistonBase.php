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
namespace pocketmine\block;

use pocketmine\block\tile\MovingBlockTile;
use pocketmine\block\tile\PistonArm;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\block\utils\PistonBlocksCalculator;
use pocketmine\block\utils\PistonPushHelper;
use pocketmine\block\utils\RedstonePowerHelper;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\Position;
use pocketmine\world\sound\PistonExtendSound;
use pocketmine\world\sound\PistonRetractSound;
use pocketmine\world\World;

abstract class PistonBase extends Opaque implements AnyFacing{
	use AnyFacingTrait {
		setFacing as protected traitSetFacing;
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
	}

	public function getFacing() : int{
		return $this->facing;
	}

	public function getPushFacing() : int{
		return Facing::axis($this->facing) === Axis::Y
			? $this->facing
			: Facing::opposite($this->facing);
	}

	public function setFacing(int $facing) : self{
		Facing::validate($facing);
		$this->traitSetFacing($facing);
		return $this;
	}

	public function isSticky() : bool{
		return false;
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::FULL;
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player !== null){
			$this->facing = $this->calculatePlacementFacing($player, $blockReplace);
		}elseif($face !== Facing::DOWN && $face !== Facing::UP){
			$this->facing = Facing::opposite($face);
		}else{
			$this->facing = Facing::UP;
		}

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onPostPlace() : void{
		$world = $this->position->getWorld();
		$world->scheduleDelayedBlockUpdate($this->position, 1);

		$tile = $this->getOrCreateArmTile();
		if($tile === null){
			return;
		}

		$powered = RedstonePowerHelper::isDirectlyPowered($this);
		$tile->setPowered($powered);
		$this->checkState($tile, $powered);
	}

	public function onNearbyBlockChange() : void{
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$tile = $this->getOrCreateArmTile();
		if($tile === null){
			return;
		}

		if($tile->tick()){
			$world->scheduleDelayedBlockUpdate($this->position, 1);
		}

		$powered = RedstonePowerHelper::isDirectlyPowered($this);
		if($tile->getState() % 2 === 0 && $tile->isPowered() !== $powered){
			if($this->checkState($tile, $powered)){
				$tile->setPowered($powered);
			}
		}
	}

	public function isExtended() : bool{
		$pushFacing = $this->getPushFacing();
		$head = $this->position->getWorld()->getBlock($this->position->getSide($pushFacing));
		return $head instanceof PistonArmCollision && $head->getFacing() === $pushFacing;
	}

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$world = $this->position->getWorld();
		if($this->isExtended()){
			$headPos = $this->position->getSide($this->getPushFacing());
			$head = $world->getBlock($headPos);
			if($head instanceof PistonArmCollision && $head->getFacing() === $this->getPushFacing()){
				$world->setBlock($headPos, VanillaBlocks::AIR());
			}
		}

		$tile = $world->getTile($this->position);
		if($tile instanceof PistonArm){
			$tile->close();
		}

		return parent::onBreak($item, $player, $returnedItems);
	}

	abstract protected function createArmCollisionBlock() : PistonArmCollision;

	private function getOrCreateArmTile() : ?PistonArm{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if($tile instanceof PistonArm){
			$tile->syncFromBlock($this);
			return $tile;
		}

		$world->setBlock($this->position, clone $this, false);
		$tile = $world->getTile($this->position);
		if($tile instanceof PistonArm){
			$tile->syncFromBlock($this);
			return $tile;
		}

		return null;
	}

	private function calculatePlacementFacing(Player $player, Block $blockReplace) : int{
		$playerPos = $player->getLocation();
		$blockPos = $blockReplace->getPosition();

		if(abs($playerPos->getFloorX() - $blockPos->x) < 2 && abs($playerPos->getFloorZ() - $blockPos->z) < 2){
			$eyeY = $playerPos->y + $player->getEyeHeight();
			if($eyeY - $blockPos->y > 2){
				return Facing::UP;
			}
			if($blockPos->y - $eyeY > 0){
				return Facing::DOWN;
			}
		}

		return Facing::opposite($player->getHorizontalFacing());
	}

	private function checkState(PistonArm $arm, bool $powered) : bool{
		$world = $this->position->getWorld();

		if($powered && !$this->isExtended()){
			if(!$this->doMove($arm, true)){
				return false;
			}
			$world->addSound($this->position, new PistonExtendSound());
			return true;
		}

		if(!$powered && $this->isExtended()){
			if(!$this->doMove($arm, false)){
				return false;
			}
			$world->addSound($this->position, new PistonRetractSound());
			return true;
		}

		return false;
	}

	private function doMove(PistonArm $arm, bool $extending) : bool{
		$calculator = new PistonBlocksCalculator($this, $extending);
		if($extending && !$calculator->canMove()){
			return false;
		}

		$world = $this->position->getWorld();
		$pushFacing = $this->getPushFacing();
		$attached = [];

		if($calculator->canMove() && ($this->isSticky() || $extending)){
			$attached = $calculator->getBlocksToMove();
			if($extending && !$this->canPlaceHeadAfterPush($attached)){
				return false;
			}

			foreach($calculator->getBlocksToDestroy() as $pos){
				PistonPushHelper::destroyBlockAt($world, $pos);
			}

			$this->spawnMovingBlocks(
				$world,
				$attached,
				$extending ? $pushFacing : Facing::opposite($pushFacing)
			);
		}

		if($extending){
			$world->setBlock(
				$this->position->getSide($pushFacing),
				$this->createArmCollisionBlock()->setFacing($pushFacing)
			);
		}

		$arm->syncFromBlock($this);
		$arm->startMove($extending, $attached);
		$world->scheduleDelayedBlockUpdate($this->position, 1);
		return true;
	}

	private function canPlaceHeadAfterPush(array $blocksToMove) : bool{
		$headPos = $this->position->getSide($this->getPushFacing());
		if($blocksToMove !== [] && PistonPushHelper::sameBlock($blocksToMove[0], $headPos)){
			return true;
		}

		$target = $this->position->getWorld()->getBlock($headPos);
		return $target->canBeReplaced() || $target->canBeFlowedInto();
	}

	private function spawnMovingBlocks(World $world, array $blocksToMove, int $side) : void{
		if($blocksToMove === []){
			return;
		}

		$pistonPos = $this->position->asVector3();
		$blocksToMove = PistonPushHelper::sortForPush($blocksToMove, $side);
		$movingBlockId = VanillaBlocks::MOVING_BLOCK()->getTypeId();
		$snapshots = [];
		$tiles = [];

		foreach($blocksToMove as $pos){
			if(PistonPushHelper::sameBlock($pos, $pistonPos)){
				continue;
			}

			$oldPos = Position::fromObject($pos, $world);
			$key = PistonPushHelper::blockKey($oldPos);
			$snapshots[$key] = clone $world->getBlock($oldPos);

			$tile = $world->getTile($oldPos);
			if($tile !== null){
				$tiles[$key] = $tile->saveNBT();
				$tile->close();
			}
		}

		foreach($blocksToMove as $pos){
			if(PistonPushHelper::sameBlock($pos, $pistonPos)){
				continue;
			}

			$oldPos = Position::fromObject($pos, $world);
			$newPos = $oldPos->getSide($side);
			$key = PistonPushHelper::blockKey($oldPos);

			if(!isset($snapshots[$key])){
				continue;
			}

			$world->setBlock($newPos, VanillaBlocks::MOVING_BLOCK());
			$movingTile = $world->getTile($newPos);
			if(!$movingTile instanceof MovingBlockTile){
				$movingTile = new MovingBlockTile($world, $newPos);
				$world->addTile($movingTile);
			}

			$movingTile->setBlock($snapshots[$key]);
			$movingTile->setPistonPos($this->position);
			if(isset($tiles[$key])){
				$movingTile->setBlockEntityNbt($tiles[$key]);
			}
			$movingTile->clearSpawnCompoundCache();

			if($world->getBlock($oldPos)->getTypeId() !== $movingBlockId){
				$world->setBlock($oldPos, VanillaBlocks::AIR());
			}
		}
	}
}
