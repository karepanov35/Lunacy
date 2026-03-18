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

use pocketmine\block\utils\FroglightType;
use pocketmine\data\runtime\RuntimeDataDescriber;

final class Froglight extends SimplePillar{

	private FroglightType $froglightType = FroglightType::OCHRE;

	public function describeBlockItemState(RuntimeDataDescriber $w) : void{
		$w->enum($this->froglightType);
	}

	public function getFroglightType() : FroglightType{ return $this->froglightType; }

	/** @return $this */
	public function setFroglightType(FroglightType $froglightType) : self{
		$this->froglightType = $froglightType;
		return $this;
	}

	public function getLightLevel() : int{
		return 15;
	}
}
