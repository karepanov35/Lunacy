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
namespace pocketmine\inventory\transaction\action;

use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;

/**
 * This action is used by creative players to balance transactions involving the creative inventory menu.
 * The source item is the item being created ("taken" from the creative menu).
 */
class CreateItemAction extends InventoryAction{

	public function __construct(Item $sourceItem){
		parent::__construct($sourceItem, VanillaItems::AIR());
	}

	public function validate(Player $source) : void{
		if($source->hasFiniteResources()){
			throw new TransactionValidationException("Player has finite resources, cannot create items");
		}
		if(!$source->getCreativeInventory()->contains($this->sourceItem)){
			throw new TransactionValidationException("Creative inventory does not contain requested item");
		}
	}

	public function execute(Player $source) : void{
		//NOOP
	}
}
