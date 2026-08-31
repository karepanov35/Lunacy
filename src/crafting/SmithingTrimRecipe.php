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

use pocketmine\item\Armor;
use pocketmine\item\ArmorTrim;
use pocketmine\item\ArmorTrimMaterial;
use pocketmine\item\ArmorTrimPattern;
use pocketmine\item\Item;

final class SmithingTrimRecipe implements SmithingRecipe{
	public function __construct(
		private RecipeIngredient $template,
		private RecipeIngredient $input,
		private RecipeIngredient $addition
	){}

	public function getTemplate() : RecipeIngredient{ return $this->template; }
	public function getInput() : RecipeIngredient{ return $this->input; }
	public function getAddition() : RecipeIngredient{ return $this->addition; }

	public function getResultFor(Item $input, Item $template, Item $addition) : Item{
		$result = clone $input;
		if($result instanceof Armor){
			$templatePattern = ArmorTrimPattern::tryFromItem($template);
			$trimMaterial = ArmorTrimMaterial::tryFromItem($addition);
			if($templatePattern !== null && $trimMaterial !== null){
				$result->setTrim(new ArmorTrim($trimMaterial, $templatePattern));
			}
		}
		return $result;
	}

	public function getIngredientList() : array{
		return [$this->template, $this->input, $this->addition];
	}

	public function getResultsFor(CraftingGrid $grid) : array{
		return [];
	}

	public function matchesCraftingGrid(CraftingGrid $grid) : bool{
		return false;
	}
}
