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

final class PotionContainerChangeRecipeData{
	/** @required */
	public string $input_item_name;

	/** @required */
	public RecipeIngredientData $ingredient;

	/** @required */
	public string $output_item_name;

	public function __construct(string $input_item_name, RecipeIngredientData $ingredient, string $output_item_name){
		$this->input_item_name = $input_item_name;
		$this->ingredient = $ingredient;
		$this->output_item_name = $output_item_name;
	}
}
