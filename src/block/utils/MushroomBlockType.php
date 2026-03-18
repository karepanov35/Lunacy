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
namespace pocketmine\block\utils;

use pocketmine\utils\LegacyEnumShimTrait;

/**
 * TODO: These tags need to be removed once we get rid of LegacyEnumShimTrait (PM6)
 *  These are retained for backwards compatibility only.
 *
 * @method static MushroomBlockType ALL_CAP()
 * @method static MushroomBlockType CAP_EAST()
 * @method static MushroomBlockType CAP_MIDDLE()
 * @method static MushroomBlockType CAP_NORTH()
 * @method static MushroomBlockType CAP_NORTHEAST()
 * @method static MushroomBlockType CAP_NORTHWEST()
 * @method static MushroomBlockType CAP_SOUTH()
 * @method static MushroomBlockType CAP_SOUTHEAST()
 * @method static MushroomBlockType CAP_SOUTHWEST()
 * @method static MushroomBlockType CAP_WEST()
 * @method static MushroomBlockType PORES()
 */
enum MushroomBlockType{
	use LegacyEnumShimTrait;

	case PORES;
	case CAP_NORTHWEST;
	case CAP_NORTH;
	case CAP_NORTHEAST;
	case CAP_WEST;
	case CAP_MIDDLE;
	case CAP_EAST;
	case CAP_SOUTHWEST;
	case CAP_SOUTH;
	case CAP_SOUTHEAST;
	case ALL_CAP;
}
