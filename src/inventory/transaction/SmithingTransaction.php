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
 * @author MaJiHoBou
 * @link https://github.com/MaJiHoBou999/Lunacy
 *
 *
 */

declare(strict_types=1);
namespace pocketmine\inventory\transaction;

use pocketmine\block\Block;
use pocketmine\crafting\SmithingRecipe;
use pocketmine\item\Item;
use pocketmine\player\Player;
use function count;

class SmithingTransaction extends InventoryTransaction{
	private Item $result;
	/** @var Item[] */
	private array $consumed = [];

	public function __construct(
		Player $source,
		protected Block $holder,
		protected Item $template,
		protected Item $input,
		protected Item $addition,
		protected SmithingRecipe $recipe,
		array $actions = []
	){
		parent::__construct($source, $actions);
		$this->consumed = [clone $this->template, clone $this->input, clone $this->addition];
		$this->result = $this->recipe->getResultFor($this->input, $this->template, $this->addition);
	}

	public function getResult() : Item{
		return $this->result;
	}

	public function validate() : void{
		$this->squashDuplicateSlotChanges();
		if(count($this->actions) < 1){
			throw new TransactionValidationException("Transaction must have at least one action to be executable");
		}

		/** @var Item[] $createdItems */
		$createdItems = [];
		/** @var Item[] $deletedItems */
		$deletedItems = [];
		$this->matchItems($createdItems, $deletedItems);

		if(count($createdItems) === 0){
			throw new TransactionValidationException("Transaction attempted to execute but did not result to anything");
		}
		if(count($createdItems) > 1){
			throw new TransactionValidationException("Transaction resulted into more than 1 item stack");
		}

		$created = $createdItems[0];
		if($created->getTypeId() !== $this->result->getTypeId() || $created->getCount() !== $this->result->getCount()){
			throw new TransactionValidationException("Transaction produced a different output item");
		}

		$expectedConsumed = [];
		foreach($this->consumed as $consumed){
			if(!$consumed->isNull()){
				$expectedConsumed[] = clone $consumed;
			}
		}

		foreach($deletedItems as $deleted){
			$remaining = $deleted->getCount();
			foreach($expectedConsumed as $idx => $expected){
				if(!$deleted->canStackWith($expected)){
					continue;
				}
				$matched = min($remaining, $expected->getCount());
				$remaining -= $matched;
				$expected->setCount($expected->getCount() - $matched);
				if($expected->getCount() <= 0){
					unset($expectedConsumed[$idx]);
				}
				if($remaining <= 0){
					break;
				}
			}
			if($remaining > 0){
				throw new TransactionValidationException("Transaction consumed more than required items");
			}
		}
	}
}
