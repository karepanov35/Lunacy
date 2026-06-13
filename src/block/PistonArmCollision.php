<?php

/*
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
 */

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;

class PistonArmCollision extends Transparent implements AnyFacing{
	use AnyFacingTrait {
		setFacing as protected traitSetFacing;
	}

	protected bool $sticky = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
	}

	public function isSticky() : bool{
		return $this->sticky;
	}

	/** @return $this */
	public function setSticky(bool $sticky) : self{
		$this->sticky = $sticky;
		return $this;
	}

	/** @return $this */
	public function setFacing(int $facing) : self{
		Facing::validate($facing);
		$this->traitSetFacing($facing);
		return $this;
	}

	public function asItem() : Item{
		return VanillaBlocks::AIR()->asItem();
	}

	public function getDrops(Item $item) : array{
		return [];
	}
}
