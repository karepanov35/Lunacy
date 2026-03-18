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
namespace pocketmine\world\generator\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;

final class TreeFactory{

	/**
	 * @param TreeType|null $type default oak
	 */
	public static function get(Random $random, ?TreeType $type = null) : ?Tree{
		return match($type){
			null, TreeType::OAK => $random->nextBoundedInt(10) === 0 ? new BigTree() : new OakTree(), // 1/10 chance for big oak
			TreeType::SPRUCE => new SpruceTree(),
			TreeType::JUNGLE => new JungleTree(),
			TreeType::ACACIA => new AcaciaTree(),
			TreeType::BIRCH => new BirchTree($random->nextBoundedInt(39) === 0),
			TreeType::DARK_OAK => new DarkOakTree(), // Added dark oak support
			TreeType::CRIMSON => new NetherTree(VanillaBlocks::CRIMSON_STEM(), VanillaBlocks::NETHER_WART_BLOCK(), VanillaBlocks::SHROOMLIGHT(), ($random->nextBoundedInt(9) + 4) * ($random->nextBoundedInt(12) === 0 ? 2 : 1), hasVines: true, huge: $random->nextFloat() < 0.06),
			TreeType::WARPED => new NetherTree(VanillaBlocks::WARPED_STEM(), VanillaBlocks::WARPED_WART_BLOCK(), VanillaBlocks::SHROOMLIGHT(), ($random->nextBoundedInt(9) + 4) * ($random->nextBoundedInt(12) === 0 ? 2 : 1), hasVines: false, huge: $random->nextFloat() < 0.06),
			default => null,
		};
	}
}
