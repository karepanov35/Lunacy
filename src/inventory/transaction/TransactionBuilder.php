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
namespace pocketmine\inventory\transaction;

use pocketmine\inventory\Inventory;
use pocketmine\inventory\transaction\action\InventoryAction;
use function spl_object_id;

final class TransactionBuilder{

	/** @var TransactionBuilderInventory[] */
	private array $inventories = [];

	/** @var InventoryAction[] */
	private array $extraActions = [];

	public function addAction(InventoryAction $action) : void{
		$this->extraActions[spl_object_id($action)] = $action;
	}

	public function getInventory(Inventory $inventory) : TransactionBuilderInventory{
		$id = spl_object_id($inventory);
		return $this->inventories[$id] ??= new TransactionBuilderInventory($inventory);
	}

	/**
	 * @return InventoryAction[]
	 */
	public function generateActions() : array{
		$actions = $this->extraActions;

		foreach($this->inventories as $inventory){
			foreach($inventory->generateActions() as $action){
				$actions[spl_object_id($action)] = $action;
			}
		}

		return $actions;
	}
}
