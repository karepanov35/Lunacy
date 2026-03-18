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
namespace pocketmine\data\bedrock\block;

/**
 * Implementors of this interface decide how a block should be deserialized and represented at runtime. This is used by
 * world providers when decoding blockstates into block IDs.
 *
 * @phpstan-type BlockStateId int
 */
interface BlockStateDeserializer{
	/**
	 * Deserializes blockstate NBT into an implementation-defined blockstate ID, for runtime paletted storage.
	 *
	 * @phpstan-return BlockStateId
	 * @throws BlockStateDeserializeException
	 */
	public function deserialize(BlockStateData $stateData) : int;
}
