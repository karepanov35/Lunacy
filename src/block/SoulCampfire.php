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

use pocketmine\crafting\FurnaceType;
use pocketmine\item\Item;

class SoulCampfire extends Campfire{

	public function getLightLevel() : int{
		return $this->lit ? 10 : 0;
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		return [
			VanillaBlocks::SOUL_SOIL()->asItem()
		];
	}

	protected function getEntityCollisionDamage() : int{
		return 2;
	}

	protected function getFurnaceType() : FurnaceType{
		return FurnaceType::SOUL_CAMPFIRE;
	}
}
