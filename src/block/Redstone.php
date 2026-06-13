<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\block\utils\AnalogRedstoneSignalEmitterTrait;
use pocketmine\block\utils\RedstoneUpdater;

class Redstone extends Opaque implements AnalogRedstoneSignalEmitter{
	use AnalogRedstoneSignalEmitterTrait;

	public function getOutputSignalStrength() : int{
		return 15;
	}

	public function onPostPlace() : void{
		RedstoneUpdater::notifyAround($this);
	}

	public function onNearbyBlockChange() : void{
		RedstoneUpdater::notifyAround($this);
	}
}
