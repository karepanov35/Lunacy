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

interface SmithingRecipe extends CraftingRecipe{
	public function getResultFor(Item $input, Item $template, Item $addition) : Item;
}
