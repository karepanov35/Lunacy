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

use pocketmine\block\utils\MultiAnyFacing;
use pocketmine\block\utils\MultiAnySupportTrait;
use pocketmine\block\utils\SupportType;

final class ResinClump extends Transparent implements MultiAnyFacing{
	use MultiAnySupportTrait;

	public function isSolid() : bool{
		return false;
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function canBeReplaced() : bool{
		return true;
	}

	/**
	 * @return int[]
	 */
	protected function getInitialPlaceFaces(Block $blockReplace) : array{
		return $blockReplace instanceof ResinClump ? $blockReplace->faces : [];
	}

	protected function recalculateCollisionBoxes() : array{
		return [];
	}
}
