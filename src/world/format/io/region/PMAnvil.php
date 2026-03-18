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
namespace pocketmine\world\format\io\region;

use pocketmine\block\Block;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;

/**
 * This format is exactly the same as the PC Anvil format, with the only difference being that the stored data order
 * is XZY instead of YZX for more performance loading and saving worlds.
 */
class PMAnvil extends RegionWorldProvider{
	use LegacyAnvilChunkTrait;

	protected function deserializeSubChunk(CompoundTag $subChunk, PalettedBlockArray $biomes3d, \Logger $logger) : SubChunk{
		return new SubChunk(Block::EMPTY_STATE_ID, [$this->palettizeLegacySubChunkXZY(
			self::readFixedSizeByteArray($subChunk, "Blocks", 4096),
			self::readFixedSizeByteArray($subChunk, "Data", 2048),
			$logger
		)], $biomes3d);
	}

	protected static function getRegionFileExtension() : string{
		return "mcapm";
	}

	protected static function getPcWorldFormatVersion() : int{
		return -1; //Not a PC format, only PocketMine-MP
	}

	public function getWorldMinY() : int{
		return 0;
	}

	public function getWorldMaxY() : int{
		return 256;
	}
}
