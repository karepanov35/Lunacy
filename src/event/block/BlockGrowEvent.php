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
namespace pocketmine\event\block;

use pocketmine\block\Block;
use pocketmine\player\Player;

/**
 * Called when plants or crops grow.
 */
class BlockGrowEvent extends BaseBlockChangeEvent{

	public function __construct(
		Block $block,
		Block $newState,
		private ?Player $player = null,
	){
		parent::__construct($block, $newState);
	}

	/**
	 * It returns the player which grows the crop.
	 * It returns null when the crop grows by itself.
	 */
	public function getPlayer() : ?Player{
		return $this->player;
	}
}
