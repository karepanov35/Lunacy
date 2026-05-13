<?php

declare(strict_types=1);

namespace pocketmine\entity\ai;

use pocketmine\entity\Living;

interface Goal{
	public function getPriority() : int;

	/**
	 * @param array<string, mixed> $memory
	 */
	public function canRun(Living $entity, array $memory) : bool;

	/**
	 * @param array<string, mixed> $memory
	 */
	public function tick(Living $entity, array $memory, int $tickDiff) : void;
}

