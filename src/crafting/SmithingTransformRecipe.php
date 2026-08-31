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
namespace pocketmine\crafting;

use pocketmine\item\Item;
use pocketmine\utils\Utils;

final class SmithingTransformRecipe implements SmithingRecipe{
	public function __construct(
		private RecipeIngredient $template,
		private RecipeIngredient $input,
		private RecipeIngredient $addition,
		private Item $output
	){}

	public function getTemplate() : RecipeIngredient{ return $this->template; }
	public function getInput() : RecipeIngredient{ return $this->input; }
	public function getAddition() : RecipeIngredient{ return $this->addition; }
	public function getOutput() : Item{ return clone $this->output; }

	public function getResultFor(Item $input, Item $template, Item $addition) : Item{
		$result = clone $this->output;
		$this->copySmithingState($input, $result);
		return $result;
	}

	private function copySmithingState(Item $from, Item $to) : void{
		$to->setCustomName($from->getCustomName());
		$to->setLore($from->getLore());
		$to->setNamedTag(clone $from->getNamedTag());
		if($from instanceof \pocketmine\item\Durable && $to instanceof \pocketmine\item\Durable){
			$to->setDamage($from->getDamage());
			$to->setUnbreakable($from->isUnbreakable());
		}
	}

	public function getIngredientList() : array{
		return [$this->template, $this->input, $this->addition];
	}

	public function getResultsFor(CraftingGrid $grid) : array{
		return [$this->getOutput()];
	}

	public function matchesCraftingGrid(CraftingGrid $grid) : bool{
		return false;
	}
}
