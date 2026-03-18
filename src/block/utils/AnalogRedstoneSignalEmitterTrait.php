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
namespace pocketmine\block\utils;

use pocketmine\data\runtime\RuntimeDataDescriber;

trait AnalogRedstoneSignalEmitterTrait{
	protected int $signalStrength = 0;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, 15, $this->signalStrength);
	}

	public function getOutputSignalStrength() : int{ return $this->signalStrength; }

	/** @return $this */
	public function setOutputSignalStrength(int $signalStrength) : self{
		if($signalStrength < 0 || $signalStrength > 15){
			throw new \InvalidArgumentException("Signal strength must be in range 0-15");
		}
		$this->signalStrength = $signalStrength;
		return $this;
	}
}
