<?php

declare(strict_types=1);

namespace pocketmine\entity\ai;

use pocketmine\entity\Living;

interface Sensor{
	/**
	 * @param array<string, mixed> $memory
	 */
	public function collect(Living $entity, array &$memory) : void;
}

