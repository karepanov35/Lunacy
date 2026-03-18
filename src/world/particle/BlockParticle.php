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
namespace pocketmine\world\particle;

use pocketmine\block\Block;
use pocketmine\network\mcpe\convert\BlockTranslator;

abstract class BlockParticle implements Particle{

	private BlockTranslator $blockTranslator;

	public function __construct(protected Block $b){}

	public function setBlockTranslator(BlockTranslator $blockTranslator) : void{
		$this->blockTranslator = $blockTranslator;
	}
	public function toRuntimeId() : int{
		return $this->blockTranslator->internalIdToNetworkId($this->b->getStateId());
	}
}
