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

use pocketmine\block\tile\Hopper as TileHopper;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class Hopper extends Transparent implements PoweredByRedstone{
	use PoweredByRedstoneTrait;

	private int $facing = Facing::DOWN;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facingExcept($this->facing, Facing::UP);
		$w->bool($this->powered);
	}

	public function getFacing() : int{ return $this->facing; }

	/** @return $this */
	public function setFacing(int $facing) : self{
		if($facing === Facing::UP){
			throw new \InvalidArgumentException("Hopper may not face upward");
		}
		$this->facing = $facing;
		return $this;
	}

	protected function recalculateCollisionBoxes() : array{
		$result = [
			AxisAlignedBB::one()->trim(Facing::UP, 6 / 16) //the empty area around the bottom is currently considered solid
		];

		foreach(Facing::HORIZONTAL as $f){ //add the frame parts around the bowl
			$result[] = AxisAlignedBB::one()->trim($f, 14 / 16);
		}
		return $result;
	}

	public function getSupportType(int $facing) : SupportType{
		return match($facing){
			Facing::UP => SupportType::FULL,
			Facing::DOWN => $this->facing === Facing::DOWN ? SupportType::CENTER : SupportType::NONE,
			default => SupportType::NONE
		};
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->facing = $face === Facing::DOWN ? Facing::DOWN : Facing::opposite($face);

		if(parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player)){
			// Запускаем обновления сразу после установки
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
			return true;
		}
		return false;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player !== null){
			$tile = $this->position->getWorld()->getTile($this->position);
			if($tile instanceof TileHopper){
				$player->setCurrentWindow($tile->getInventory());
			}
			return true;
		}
		return false;
	}
	
	public function onNearbyBlockChange() : void{
		// Запускаем обновление при изменении соседних блоков
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
	}

	public function onScheduledUpdate() : void{
		// Если воронка запитана редстоуном - не работает
		if($this->powered){
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 8);
			return;
		}
		
		$tile = $this->position->getWorld()->getTile($this->position);
		if(!($tile instanceof TileHopper)){
			// Если тайла нет, пробуем снова через 20 тиков
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 20);
			return;
		}
		
		$inventory = $tile->getInventory();
		
		// Всасываем предметы сверху
		$this->suckItems($inventory);
		
		// Планируем следующее обновление (каждые 8 тиков = 0.4 секунды)
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 8);
	}
	
	private function suckItems(\pocketmine\inventory\Inventory $inventory) : void{
		$world = $this->position->getWorld();
		
		// Создаем область всасывания (1 блок над воронкой + немного шире)
		$pos = $this->position;
		$bb = new AxisAlignedBB(
			$pos->x - 0.5,
			$pos->y + 0.5,
			$pos->z - 0.5,
			$pos->x + 1.5,
			$pos->y + 2,
			$pos->z + 1.5
		);
		
		// Ищем предметы в этой области
		$entities = $world->getNearbyEntities($bb);
		
		foreach($entities as $entity){
			if(!($entity instanceof ItemEntity)){
				continue;
			}
			
			// Проверяем что предмет не в кулдауне
			if($entity->getPickupDelay() > 0){
				continue;
			}
			
			$item = $entity->getItem();
			
			// Пытаемся добавить предмет в инвентарь воронки
			if($inventory->canAddItem($item)){
				$inventory->addItem($item);
				$entity->flagForDespawn();
				break; // Всасываем только 1 предмет за раз
			}
		}
	}
}
