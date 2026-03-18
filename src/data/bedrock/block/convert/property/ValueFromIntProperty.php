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
namespace pocketmine\data\bedrock\block\convert\property;

use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use function array_keys;

/**
 * @phpstan-template TBlock of object
 * @phpstan-template TValue of int|\UnitEnum
 * @phpstan-implements Property<TBlock>
 */
final class ValueFromIntProperty implements Property{

	/**
	 * @phpstan-param StateMap<TValue, int>            $map
	 * @phpstan-param \Closure(TBlock) : TValue        $getter
	 * @phpstan-param \Closure(TBlock, TValue) : mixed $setter
	 */
	public function __construct(
		private string $name,
		private StateMap $map,
		private \Closure $getter,
		private \Closure $setter
	){}

	public function getName() : string{ return $this->name; }

	/**
	 * @return int[]
	 * @phpstan-return list<int>
	 */
	public function getPossibleValues() : array{
		return array_keys($this->map->getRawToValueMap());
	}

	public function deserialize(object $block, BlockStateReader $in) : void{
		$raw = $in->readInt($this->name);
		$value = $this->map->rawToValue($raw);

		if($value === null){
			throw $in->badValueException($this->name, (string) $raw);
		}
		($this->setter)($block, $value);
	}

	public function serialize(object $block, BlockStateWriter $out) : void{
		$value = ($this->getter)($block);
		$raw = $this->map->valueToRaw($value);

		$out->writeInt($this->name, $raw);
	}
}
