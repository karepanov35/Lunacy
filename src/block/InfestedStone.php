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

use pocketmine\item\Item;

class InfestedStone extends Opaque{

	private int $imitated;

	public function __construct(BlockIdentifier $idInfo, string $name, BlockTypeInfo $typeInfo, Block $imitated){
		parent::__construct($idInfo, $name, $typeInfo);
		$this->imitated = $imitated->getStateId();
	}

	public function getImitatedBlock() : Block{
		return RuntimeBlockStateRegistry::getInstance()->fromStateId($this->imitated);
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		return [];
	}

	public function getSilkTouchDrops(Item $item) : array{
		return [$this->getImitatedBlock()->asItem()];
	}

	public function isAffectedBySilkTouch() : bool{
		return true;
	}

	//TODO
}
