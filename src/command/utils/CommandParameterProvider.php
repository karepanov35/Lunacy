<?php

declare(strict_types=1);

namespace pocketmine\command\utils;

interface CommandParameterProvider{
	/**
	 * @return \pocketmine\network\mcpe\protocol\types\command\CommandOverload[]
	 */
	public function getCommandOverloads(int $protocolId) : array;
}
