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

namespace pocketmine\block\tile;

use pocketmine\block\PistonArmCollision;
use pocketmine\block\PistonBase;
use pocketmine\block\utils\PistonPushHelper;
use pocketmine\block\utils\RedstoneUpdater;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;

class PistonArm extends Spawnable{

	public const MOVE_STEP = 0.5;

	private const TAG_STATE = "State";
	private const TAG_NEW_STATE = "NewState";
	private const TAG_PROGRESS = "Progress";
	private const TAG_LAST_PROGRESS = "LastProgress";
	private const TAG_STICKY = "Sticky";
	private const TAG_EXTENDING = "Extending";
	private const TAG_POWERED = "powered";
	private const TAG_FACING = "facing";
	private const TAG_ATTACHED = "AttachedBlocks";
	private const TAG_MOVABLE = "isMovable";
	private const TAG_BREAK_BLOCKS = "BreakBlocks";

	private int $state = 0;
	private int $newState = 0;
	private float $progress = 0.0;
	private float $lastProgress = 0.0;
	private bool $sticky = false;
	private bool $extending = false;
	private bool $powered = false;
	private bool $movable = true;
	private int $facing = Facing::DOWN;

	private array $attachedBlocks = [];

	public function readSaveData(CompoundTag $nbt) : void{
		$this->state = $nbt->getByte(self::TAG_STATE, 0);
		$this->newState = $nbt->getByte(self::TAG_NEW_STATE, 0);
		$this->progress = $nbt->getFloat(self::TAG_PROGRESS, 0.0);
		$this->lastProgress = $nbt->getFloat(self::TAG_LAST_PROGRESS, 0.0);
		$this->sticky = $nbt->getByte(self::TAG_STICKY) !== 0;
		$this->extending = $nbt->getByte(self::TAG_EXTENDING) !== 0;
		$this->powered = $nbt->getByte(self::TAG_POWERED) !== 0;
		$this->movable = $nbt->getByte(self::TAG_MOVABLE, 1) !== 0;
		$this->facing = $nbt->getInt(self::TAG_FACING, Facing::DOWN);
		$this->attachedBlocks = self::readAttachedBlocks($nbt->getListTag(self::TAG_ATTACHED));

		if($this->state === 1 || $this->state === 3 || $this->attachedBlocks !== []){
			$this->lastProgress = $this->extending
				? max(0.0, $this->progress - self::MOVE_STEP)
				: min(1.0, $this->progress + self::MOVE_STEP);
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setByte(self::TAG_STATE, $this->state);
		$nbt->setByte(self::TAG_NEW_STATE, $this->newState);
		$nbt->setFloat(self::TAG_PROGRESS, $this->progress);
		$nbt->setFloat(self::TAG_LAST_PROGRESS, $this->lastProgress);
		$nbt->setByte(self::TAG_STICKY, $this->sticky ? 1 : 0);
		$nbt->setByte(self::TAG_EXTENDING, $this->extending ? 1 : 0);
		$nbt->setByte(self::TAG_POWERED, $this->powered ? 1 : 0);
		$nbt->setByte(self::TAG_MOVABLE, $this->movable ? 1 : 0);
		$nbt->setInt(self::TAG_FACING, $this->facing);
		$nbt->setTag(self::TAG_ATTACHED, self::writeAttachedBlocks($this->attachedBlocks));
	}

	public function syncFromBlock(PistonBase $block) : void{
		$this->facing = $block->getPushFacing();
		$this->sticky = $block->isSticky();
	}

	public function getState() : int{
		return $this->state;
	}

	public function isPowered() : bool{
		return $this->powered;
	}

	public function setPowered(bool $powered) : void{
		$this->powered = $powered;
	}

	public function startMove(bool $extending, array $attachedBlocks) : void{
		$this->extending = $extending;
		$this->attachedBlocks = $attachedBlocks;
		$this->movable = false;
		$this->progress = $extending ? 0.0 : 1.0;
		$this->lastProgress = $extending ? -self::MOVE_STEP : 1.0 + self::MOVE_STEP;
		$this->state = $this->newState = $extending ? 1 : 3;
		$this->syncToClients();
	}

	public function tick() : bool{
		if($this->state % 2 === 0){
			return false;
		}

		if($this->extending){
			$this->progress = min(1.0, $this->progress + self::MOVE_STEP);
			$this->lastProgress = min(1.0, $this->lastProgress + self::MOVE_STEP);
		}else{
			$this->progress = max(0.0, $this->progress - self::MOVE_STEP);
			$this->lastProgress = max(0.0, $this->lastProgress - self::MOVE_STEP);
		}

		if(abs($this->progress - $this->lastProgress) < 0.00001){
			$this->finishMove();
			return false;
		}

		$this->syncToClients();
		return true;
	}

	public function syncToClients() : void{
		$this->clearSpawnCompoundCache();
		$this->position->getWorld()->broadcastPacketToViewersByTypeConverter(
			$this->position,
			fn(TypeConverter $typeConverter) : array => [
				BlockActorDataPacket::create(
					BlockPosition::fromVector3($this->position),
					$this->getSerializedSpawnCompound($typeConverter)
				),
			]
		);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt, TypeConverter $typeConverter) : void{
		$block = $this->position->getWorld()->getBlock($this->position);
		if($block instanceof PistonBase){
			$this->syncFromBlock($block);
		}

		$nbt->setFloat(self::TAG_PROGRESS, $this->progress);
		$nbt->setFloat(self::TAG_LAST_PROGRESS, $this->lastProgress);
		$nbt->setByte(self::TAG_STATE, $this->state);
		$nbt->setByte(self::TAG_NEW_STATE, $this->newState);
		$nbt->setByte(self::TAG_STICKY, $this->sticky ? 1 : 0);
		$nbt->setByte(self::TAG_EXTENDING, $this->extending ? 1 : 0);
		$nbt->setByte(self::TAG_MOVABLE, $this->movable ? 1 : 0);
		$nbt->setTag(self::TAG_ATTACHED, self::writeAttachedBlocks($this->attachedBlocks));
		$nbt->setTag(self::TAG_BREAK_BLOCKS, new ListTag());
	}

	private function finishMove() : void{
		$world = $this->position->getWorld();
		$pushDir = $this->extending ? $this->facing : Facing::opposite($this->facing);
		$attached = PistonPushHelper::sortForPush($this->attachedBlocks, $pushDir);

		foreach($attached as $pos){
			$movingPos = $pos->getSide($pushDir);
			$tile = $world->getTile($movingPos);
			if(!$tile instanceof MovingBlockTile){
				continue;
			}

			$block = $tile->getBlock();
			$entityNbt = $tile->getBlockEntityNbt();
			$tile->close();

			$world->setBlock($movingPos, $block);
			RedstoneUpdater::updateAt($world, $movingPos);

			if($entityNbt !== null){
				$entityNbt->setInt(Tile::TAG_X, $movingPos->getFloorX());
				$entityNbt->setInt(Tile::TAG_Y, $movingPos->getFloorY());
				$entityNbt->setInt(Tile::TAG_Z, $movingPos->getFloorZ());
				$newTile = TileFactory::getInstance()->createFromData($world, $entityNbt);
				if($newTile !== null){
					$world->addTile($newTile);
				}
			}
		}

		if($this->extending){
			$this->progress = 1.0;
			$this->lastProgress = 1.0;
			$this->state = $this->newState = 2;
		}else{
			$headPos = $this->position->getSide($this->facing);
			$head = $world->getBlock($headPos);
			if($head instanceof PistonArmCollision && $head->getFacing() === $this->facing){
				$world->setBlock($headPos, VanillaBlocks::AIR());
			}
			$this->progress = 0.0;
			$this->lastProgress = 0.0;
			$this->state = $this->newState = 0;
			$this->movable = true;
		}

		$this->attachedBlocks = [];
		$this->syncToClients();
		RedstoneUpdater::notifyAround($world->getBlock($this->position));
		$world->scheduleDelayedBlockUpdate($this->position, 1);
	}

	private static function readAttachedBlocks(?ListTag $list) : array{
		if($list === null){
			return [];
		}

		$values = [];
		foreach($list as $tag){
			if($tag instanceof IntTag){
				$values[] = $tag->getValue();
			}
		}

		$blocks = [];
		for($i = 0, $count = count($values); $i + 2 < $count; $i += 3){
			$blocks[] = new Vector3($values[$i], $values[$i + 1], $values[$i + 2]);
		}

		return $blocks;
	}

	private static function writeAttachedBlocks(array $blocks) : ListTag{
		$list = new ListTag();
		foreach($blocks as $pos){
			$list->push(new IntTag($pos->getFloorX()));
			$list->push(new IntTag($pos->getFloorY()));
			$list->push(new IntTag($pos->getFloorZ()));
		}
		return $list;
	}
}
