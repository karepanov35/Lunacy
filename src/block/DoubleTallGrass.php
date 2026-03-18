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
namespace pocketmine\block;

use pocketmine\block\utils\TallGrassTrait;
use pocketmine\item\Item;

class DoubleTallGrass extends DoublePlant{
	use TallGrassTrait {
		getDropsForIncompatibleTool as traitGetDropsForIncompatibleTool;
	}

	public function canBeReplaced() : bool{
		return true;
	}

	public function getDropsForIncompatibleTool(Item $item) : array{
		if($this->top){
			return $this->traitGetDropsForIncompatibleTool($item);
		}
		return [];
	}
}
