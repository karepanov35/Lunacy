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
namespace pocketmine\event\player;

use pocketmine\block\Block;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;

class PlayerRespawnAnchorUseEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;

	public const ACTION_EXPLODE = 0;
	public const ACTION_SET_SPAWN = 1;

	public function __construct(
		Player $player,
		protected Block $block,
		private int $action = self::ACTION_EXPLODE
	){
		$this->player = $player;
	}

	public function getBlock() : Block{
		return $this->block;
	}

	public function getAction() : int{
		return $this->action;
	}

	public function setAction(int $action) : void{
		$this->action = $action;
	}
}
