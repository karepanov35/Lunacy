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
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

/**
 * Called when structures such as Saplings or Bamboo grow.
 * These types of plants tend to change multiple blocks at once upon growing.
 */
class StructureGrowEvent extends BlockEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Block $block,
		private BlockTransaction $transaction,
		private ?Player $player
	){
		parent::__construct($block);
	}

	public function getTransaction() : BlockTransaction{
		return $this->transaction;
	}

	/**
	 * It returns the player which grows the structure.
	 * It returns null when the structure grows by itself.
	 */
	public function getPlayer() : ?Player{
		return $this->player;
	}
}
