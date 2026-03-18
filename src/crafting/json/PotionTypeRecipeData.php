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
namespace pocketmine\crafting\json;

final class PotionTypeRecipeData{
	/** @required */
	public RecipeIngredientData $input;

	/** @required */
	public RecipeIngredientData $ingredient;

	/** @required */
	public ItemStackData $output;

	public function __construct(RecipeIngredientData $input, RecipeIngredientData $ingredient, ItemStackData $output){
		$this->input = $input;
		$this->ingredient = $ingredient;
		$this->output = $output;
	}
}
