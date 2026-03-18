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
namespace pocketmine\world\format\io;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\format\SubChunk;

final class ChunkData{

	/**
	 * @param SubChunk[]    $subChunks
	 * @param CompoundTag[] $entityNBT
	 * @param CompoundTag[] $tileNBT
	 *
	 * @phpstan-param array<int, SubChunk> $subChunks
	 * @phpstan-param list<CompoundTag> $entityNBT
	 * @phpstan-param list<CompoundTag> $tileNBT
	 */
	public function __construct(
		private array $subChunks,
		private bool $populated,
		private array $entityNBT,
		private array $tileNBT
	){}

	/**
	 * @return SubChunk[]
	 * @phpstan-return array<int, SubChunk>
	 */
	public function getSubChunks() : array{ return $this->subChunks; }

	public function isPopulated() : bool{ return $this->populated; }

	/**
	 * @return CompoundTag[]
	 * @phpstan-return list<CompoundTag>
	 */
	public function getEntityNBT() : array{ return $this->entityNBT; }

	/**
	 * @return CompoundTag[]
	 * @phpstan-return list<CompoundTag>
	 */
	public function getTileNBT() : array{ return $this->tileNBT; }
}
