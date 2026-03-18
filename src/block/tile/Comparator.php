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
namespace pocketmine\block\tile;

use pocketmine\block\RedstoneComparator;
use pocketmine\nbt\tag\CompoundTag;

/**
 * @deprecated
 * @see RedstoneComparator
 */
class Comparator extends Tile{
	private const TAG_OUTPUT_SIGNAL = "OutputSignal"; //int

	protected int $signalStrength = 0;

	public function getSignalStrength() : int{
		return $this->signalStrength;
	}

	public function setSignalStrength(int $signalStrength) : void{
		$this->signalStrength = $signalStrength;
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->signalStrength = $nbt->getInt(self::TAG_OUTPUT_SIGNAL, 0);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setInt(self::TAG_OUTPUT_SIGNAL, $this->signalStrength);
	}
}
