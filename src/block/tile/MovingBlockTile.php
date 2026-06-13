<?php

/*
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
 */

declare(strict_types=1);

namespace pocketmine\block\tile;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\Position;

class MovingBlockTile extends Spawnable{

	private const TAG_PISTON_X = "pistonPosX";
	private const TAG_PISTON_Y = "pistonPosY";
	private const TAG_PISTON_Z = "pistonPosZ";
	private const TAG_MOVING_BLOCK = "movingBlock";
	private const TAG_MOVING_ENTITY = "movingEntity";

	private Block $block;
	private Position $pistonPos;
	private ?CompoundTag $blockEntityNbt = null;

	public function setBlock(Block $block) : void{
		$this->block = $block;
		$this->clearSpawnCompoundCache();
	}

	public function getBlock() : Block{
		return $this->block;
	}

	public function setPistonPos(Position $pistonPos) : void{
		$this->pistonPos = $pistonPos;
		$this->clearSpawnCompoundCache();
	}

	public function getPistonPos() : Position{
		return $this->pistonPos;
	}

	public function setBlockEntityNbt(?CompoundTag $nbt) : void{
		$this->blockEntityNbt = $nbt;
	}

	public function getBlockEntityNbt() : ?CompoundTag{
		return $this->blockEntityNbt;
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->pistonPos = new Position(
			$nbt->getInt(self::TAG_PISTON_X),
			$nbt->getInt(self::TAG_PISTON_Y),
			$nbt->getInt(self::TAG_PISTON_Z),
			$this->position->getWorld()
		);

		$movingBlock = $nbt->getCompoundTag(self::TAG_MOVING_BLOCK);
		$this->block = $movingBlock !== null
			? GlobalBlockStateHandlers::getDeserializer()->deserialize(BlockStateData::fromNbt($movingBlock))
			: VanillaBlocks::AIR();

		$this->blockEntityNbt = $nbt->getCompoundTag(self::TAG_MOVING_ENTITY);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		self::writePistonPos($nbt, $this->pistonPos);
		$nbt->setTag(self::TAG_MOVING_BLOCK, $this->serializeBlockState()->toVanillaNbt());
		if($this->blockEntityNbt !== null){
			$nbt->setTag(self::TAG_MOVING_ENTITY, $this->blockEntityNbt);
		}
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt, TypeConverter $typeConverter) : void{
		self::writePistonPos($nbt, $this->pistonPos);
		$nbt->setTag(self::TAG_MOVING_BLOCK, $this->serializeBlockState()->toVanillaNbt());
		if($this->blockEntityNbt !== null){
			$nbt->setTag(self::TAG_MOVING_ENTITY, $this->blockEntityNbt);
		}
	}

	private function serializeBlockState() : BlockStateData{
		return GlobalBlockStateHandlers::getSerializer()->serialize($this->block->getStateId());
	}

	private static function writePistonPos(CompoundTag $nbt, Position $pistonPos) : void{
		$nbt->setInt(self::TAG_PISTON_X, $pistonPos->getFloorX());
		$nbt->setInt(self::TAG_PISTON_Y, $pistonPos->getFloorY());
		$nbt->setInt(self::TAG_PISTON_Z, $pistonPos->getFloorZ());
	}
}
