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


use pocketmine\block\tile\Chest as TileChest;
use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use function count;

class TrappedChest extends Chest implements AnalogRedstoneSignalEmitter{

	private ?int $lastSignal = null;

	public function getOutputSignalStrength() : int{
		$tile = $this->position->getWorld()->getTile($this->position);
		if(!$tile instanceof TileChest){
			return 0;
		}

		return count($tile->getInventory()->getViewers()) > 0 ? 1 : 0;
	}

	public function setOutputSignalStrength(int $signalStrength) : self{
		return $this;
	}

	public function onScheduledUpdate() : void{
		$current = $this->getOutputSignalStrength();
		if($current !== $this->lastSignal){
			$this->lastSignal = $current;
			$this->position->getWorld()->setBlock($this->position, $this); 
		}
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 2); 
}
}