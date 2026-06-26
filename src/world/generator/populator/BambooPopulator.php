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

namespace pocketmine\world\generator\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeTags;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Bamboo as BambooBlock;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function max;
use function min;

class BambooPopulator implements Populator{
	private int $randomAmount = 0;
	private int $baseAmount = 0;
	private int $minHeight = 3;
	private int $maxHeight = 6;

	public function setRandomAmount(int $amount) : void{
		$this->randomAmount = $amount;
	}

	public function setBaseAmount(int $amount) : void{
		$this->baseAmount = $amount;
	}

	public function setHeightRange(int $minHeight, int $maxHeight) : void{
		$this->minHeight = max(1, $minHeight);
		$this->maxHeight = max($this->minHeight, $maxHeight);
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$amount = $random->nextRange(0, $this->randomAmount) + $this->baseAmount;
		if($amount <= 0){
			return;
		}

		for($i = 0; $i < $amount; ++$i){
			$x = $random->nextRange($chunkX * Chunk::EDGE_LENGTH, $chunkX * Chunk::EDGE_LENGTH + (Chunk::EDGE_LENGTH - 1));
			$z = $random->nextRange($chunkZ * Chunk::EDGE_LENGTH, $chunkZ * Chunk::EDGE_LENGTH + (Chunk::EDGE_LENGTH - 1));
			$y = $this->getHighestWorkableBlock($world, $x, $z);

			if($y === -1){
				continue;
			}

			$height = $random->nextRange($this->minHeight, $this->maxHeight);
			if(!$this->canPlaceColumn($world, $x, $y, $z, $height)){
				continue;
			}

			$this->placeBambooColumn($world, $x, $y, $z, $height);
		}
	}

	private function canPlaceColumn(ChunkManager $world, int $x, int $y, int $z, int $height) : bool{
		$maxY = $world->getMaxY();
		if($y + $height >= $maxY){
			return false;
		}
		for($dy = 0; $dy < $height; ++$dy){
			if(!$world->getBlockAt($x, $y + $dy, $z)->canBeReplaced()){
				return false;
			}
		}
		return true;
	}

	private function placeBambooColumn(ChunkManager $world, int $x, int $y, int $z, int $height) : void{
		$thick = $height >= 4;
		$bamboo = VanillaBlocks::BAMBOO()->setReady(false)->setThick($thick);

		for($dy = 0; $dy < $height; ++$dy){
			$leafSize = $this->resolveLeafSize($height, $dy);
			$block = (clone $bamboo)->setLeafSize($leafSize);
			$world->setBlockAt($x, $y + $dy, $z, $block);
		}
	}

	private function resolveLeafSize(int $height, int $dy) : int{
		$distanceFromTop = $height - 1 - $dy;
		if($height <= 2){
			return $distanceFromTop === 0 ? BambooBlock::SMALL_LEAVES : BambooBlock::NO_LEAVES;
		}
		if($height === 3){
			return $distanceFromTop <= 1 ? BambooBlock::SMALL_LEAVES : BambooBlock::NO_LEAVES;
		}
		if($height === 4){
			if($distanceFromTop === 0){
				return BambooBlock::LARGE_LEAVES;
			}
			if($distanceFromTop === 1){
				return BambooBlock::SMALL_LEAVES;
			}
			return BambooBlock::NO_LEAVES;
		}

		if($distanceFromTop === 0 || $distanceFromTop === 1){
			return BambooBlock::LARGE_LEAVES;
		}
		if($distanceFromTop === 2){
			return BambooBlock::SMALL_LEAVES;
		}
		return BambooBlock::NO_LEAVES;
	}

	private function getHighestWorkableBlock(ChunkManager $world, int $x, int $z) : int{
		$highestBlock = $world->getChunk($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE)?->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
		if($highestBlock === null){
			return -1;
		}

		for($y = min($highestBlock, $world->getMaxY() - 2); $y >= $world->getMinY(); --$y){
			$block = $world->getBlockAt($x, $y, $z);
			if($this->canGrowOn($block)){
				return $y + 1;
			}
			if(!$block->canBeReplaced()){
				return -1;
			}
		}

		return -1;
	}

	private function canGrowOn(\pocketmine\block\Block $block) : bool{
		if($block->getTypeId() === BlockTypeIds::GRAVEL){
			return true;
		}
		return $block->hasTypeTag(BlockTypeTags::DIRT)
			|| $block->hasTypeTag(BlockTypeTags::MUD)
			|| $block->hasTypeTag(BlockTypeTags::SAND);
	}
}
