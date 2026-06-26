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

namespace pocketmine\block\utils;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\PistonBase;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

final class PistonBlocksCalculator{

	private array $toMove = [];
	private array $toDestroy = [];

	private Position $pistonPos;
	private ?Position $armPos = null;
	private int $moveDirection;
	private bool $extending;
	private World $world;

	public function __construct(
		private PistonBase $piston,
		bool $extending
	){
		$this->pistonPos = $piston->getPosition();
		$this->world = $this->pistonPos->getWorld();
		$this->extending = $extending;

		$facing = $piston->getPushFacing();
		if(!$extending){
			$this->armPos = $this->pistonPos->getSide($facing);
		}

		$this->moveDirection = $extending ? $facing : Facing::opposite($facing);
	}

	public function canMove() : bool{
		if(!$this->piston->isSticky() && !$this->extending){
			return true;
		}

		$this->toMove = [];
		$this->toDestroy = [];

		$blockToMove = $this->getBlockToMove();
		if($blockToMove === null){
			return true;
		}

		$block = $this->world->getBlock($blockToMove);
		if(!PistonPushHelper::canPushBlock($block, $this->moveDirection, true, $this->extending, $this->pistonPos, $this->world)){
			return false;
		}

		if(PistonPushHelper::breaksWhenMoved($block)){
			if($this->extending || PistonPushHelper::sticksToPiston($block)){
				$this->toDestroy[] = $blockToMove->asVector3();
			}
			return true;
		}

		if(!$this->addBlockLine($blockToMove)){
			return false;
		}

		foreach($this->toMove as $pos){
			if($this->world->getBlock($pos)->getTypeId() === BlockTypeIds::SLIME && !$this->addBranchingBlocks($pos)){
				return false;
			}
		}

		return true;
	}

	public function getBlocksToMove() : array{
		return $this->toMove;
	}

	public function getBlocksToDestroy() : array{
		return $this->toDestroy;
	}

	private function getBlockToMove() : ?Position{
		$facing = $this->piston->getPushFacing();
		if($this->extending){
			return $this->pistonPos->getSide($facing);
		}
		if($this->piston->isSticky()){
			return $this->pistonPos->getSide($facing, 2);
		}
		return null;
	}

	private function addBlockLine(Position $origin) : bool{
		$block = $this->world->getBlock($origin);
		if($block->canBeReplaced() || $block->canBeFlowedInto()){
			return true;
		}

		if(!PistonPushHelper::canPushBlock($block, $this->moveDirection, false, $this->extending, $this->pistonPos, $this->world)){
			return false;
		}

		if(PistonPushHelper::sameBlock($origin, $this->pistonPos) || $this->containsMove($origin)){
			return true;
		}

		if(count($this->toMove) >= PistonPushHelper::MAX_PUSH_BLOCKS){
			return false;
		}

		$this->toMove[] = $origin->asVector3();

		$stickedCount = 0;
		$sticked = [];
		$count = 1;

		while($this->world->getBlock($origin)->getTypeId() === BlockTypeIds::SLIME){
			$stickedBlockPos = $origin->getSide(Facing::opposite($this->moveDirection), $count);
			$stickedBlock = $this->world->getBlock($stickedBlockPos);

			if($stickedBlock->canBeReplaced() || $stickedBlock->canBeFlowedInto()){
				break;
			}
			if(!PistonPushHelper::canPushBlock($stickedBlock, $this->moveDirection, false, $this->extending, $this->pistonPos, $this->world)){
				break;
			}
			if(PistonPushHelper::sameBlock($stickedBlockPos, $this->pistonPos)){
				break;
			}

			if(PistonPushHelper::breaksWhenMoved($stickedBlock) && PistonPushHelper::sticksToPiston($stickedBlock)){
				$this->toDestroy[] = $stickedBlockPos->asVector3();
				break;
			}

			if(++$count + count($this->toMove) > PistonPushHelper::MAX_PUSH_BLOCKS){
				return false;
			}

			$sticked[] = $stickedBlockPos->asVector3();
		}

		$stickedCount = count($sticked);
		if($stickedCount > 0){
			$this->toMove = [...$this->toMove, ...array_reverse($sticked)];
		}

		$step = 1;
		while(true){
			$nextPos = $origin->getSide($this->moveDirection, $step);
			$index = $this->indexOfMove($nextPos);

			if($index >= 0){
				$this->reorderListAtCollision($stickedCount, $index);
				for($i = 0, $limit = $index + $stickedCount; $i <= $limit; ++$i){
					$bPos = $this->toMove[$i];
					if($this->world->getBlock($bPos)->getTypeId() === BlockTypeIds::SLIME && !$this->addBranchingBlocks($bPos)){
						return false;
					}
				}
				return true;
			}

			$nextBlock = $this->world->getBlock($nextPos);
			if($nextBlock->canBeReplaced() || $nextBlock->canBeFlowedInto()){
				return true;
			}
			if($this->armPos !== null && PistonPushHelper::sameBlock($nextPos, $this->armPos)){
				return true;
			}
			if(!PistonPushHelper::canPushBlock($nextBlock, $this->moveDirection, true, $this->extending, $this->pistonPos, $this->world)
				|| PistonPushHelper::sameBlock($nextPos, $this->pistonPos)){
				return false;
			}

			if(PistonPushHelper::breaksWhenMoved($nextBlock)){
				$this->toDestroy[] = $nextPos->asVector3();
				return true;
			}

			if(count($this->toMove) >= PistonPushHelper::MAX_PUSH_BLOCKS){
				return false;
			}

			$this->toMove[] = $nextPos->asVector3();
			++$stickedCount;
			++$step;
		}
	}

	private function addBranchingBlocks(Vector3 $blockPos) : bool{
		foreach(Facing::ALL as $face){
			if(Facing::axis($face) === Facing::axis($this->moveDirection)){
				continue;
			}
			if(!$this->addBlockLine(Position::fromObject($blockPos, $this->world)->getSide($face))){
				return false;
			}
		}
		return true;
	}

	private function reorderListAtCollision(int $count, int $index) : void{
		$list = array_slice($this->toMove, 0, $index);
		$list1 = array_slice($this->toMove, count($this->toMove) - $count, $count);
		$list2 = array_slice($this->toMove, $index, count($this->toMove) - $count - $index);
		$this->toMove = [...$list, ...$list1, ...$list2];
	}

	private function containsMove(Vector3 $pos) : bool{
		return $this->indexOfMove($pos) >= 0;
	}

	private function indexOfMove(Vector3 $pos) : int{
		foreach($this->toMove as $i => $movePos){
			if(PistonPushHelper::sameBlock($movePos, $pos)){
				return $i;
			}
		}
		return -1;
	}
}
