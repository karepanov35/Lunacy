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
 * @author MaJiHoBou
 * @link https://github.com/karepanov35/Lunacy
 *
 *
 */

declare(strict_types=1);
namespace pocketmine\item;

use pocketmine\item\VanillaItems;
use pocketmine\utils\LegacyEnumShimTrait;

/**
 * @method static ArmorTrimPattern COAST()
 * @method static ArmorTrimPattern DUNE()
 * @method static ArmorTrimPattern EYE()
 * @method static ArmorTrimPattern FLOW()
 * @method static ArmorTrimPattern HOST()
 * @method static ArmorTrimPattern BOLT()
 * @method static ArmorTrimPattern RAISER()
 * @method static ArmorTrimPattern RIB()
 * @method static ArmorTrimPattern SENTRY()
 * @method static ArmorTrimPattern SHAPER()
 * @method static ArmorTrimPattern SILENCE()
 * @method static ArmorTrimPattern SNOUT()
 * @method static ArmorTrimPattern SPIRE()
 * @method static ArmorTrimPattern TIDE()
 * @method static ArmorTrimPattern VEX()
 * @method static ArmorTrimPattern WARD()
 * @method static ArmorTrimPattern WAYFINDER()
 * @method static ArmorTrimPattern WILD()
 */
enum ArmorTrimPattern{
	use LegacyEnumShimTrait;

	case COAST;
	case DUNE;
	case EYE;
	case FLOW;
	case HOST;
	case BOLT;
	case RAISER;
	case RIB;
	case SENTRY;
	case SHAPER;
	case SILENCE;
	case SNOUT;
	case SPIRE;
	case TIDE;
	case VEX;
	case WARD;
	case WAYFINDER;
	case WILD;

	public function getPatternId() : string{
		return match($this){
			self::COAST => "coast",
			self::DUNE => "dune",
			self::EYE => "eye",
			self::FLOW => "flow",
			self::HOST => "host",
			self::BOLT => "bolt",
			self::RAISER => "raiser",
			self::RIB => "rib",
			self::SENTRY => "sentry",
			self::SHAPER => "shaper",
			self::SILENCE => "silence",
			self::SNOUT => "snout",
			self::SPIRE => "spire",
			self::TIDE => "tide",
			self::VEX => "vex",
			self::WARD => "ward",
			self::WAYFINDER => "wayfinder",
			self::WILD => "wild",
		};
	}

	public function getItemId() : string{
		return match($this){
			self::BOLT => "minecraft:bolt_armor_trim_smithing_template",
			self::COAST => "minecraft:coast_armor_trim_smithing_template",
			self::DUNE => "minecraft:dune_armor_trim_smithing_template",
			self::EYE => "minecraft:eye_armor_trim_smithing_template",
			self::HOST => "minecraft:host_armor_trim_smithing_template",
			self::RAISER => "minecraft:raiser_armor_trim_smithing_template",
			self::RIB => "minecraft:rib_armor_trim_smithing_template",
			self::SENTRY => "minecraft:sentry_armor_trim_smithing_template",
			self::SHAPER => "minecraft:shaper_armor_trim_smithing_template",
			self::SILENCE => "minecraft:silence_armor_trim_smithing_template",
			self::SNOUT => "minecraft:snout_armor_trim_smithing_template",
			self::SPIRE => "minecraft:spire_armor_trim_smithing_template",
			self::TIDE => "minecraft:tide_armor_trim_smithing_template",
			self::VEX => "minecraft:vex_armor_trim_smithing_template",
			self::WARD => "minecraft:ward_armor_trim_smithing_template",
			self::WAYFINDER => "minecraft:wayfinder_armor_trim_smithing_template",
			self::WILD => "minecraft:wild_armor_trim_smithing_template",
		};
	}

	public static function tryFromItem(Item $item) : ?self{
		return match(true){
			$item->canStackWith(VanillaItems::COAST_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::COAST,
			$item->canStackWith(VanillaItems::DUNE_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::DUNE,
			$item->canStackWith(VanillaItems::EYE_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::EYE,
			$item->canStackWith(VanillaItems::FLOW_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::FLOW,
			$item->canStackWith(VanillaItems::HOST_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::HOST,
			$item->canStackWith(VanillaItems::BOLT_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::BOLT,
			$item->canStackWith(VanillaItems::RAISER_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::RAISER,
			$item->canStackWith(VanillaItems::RIB_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::RIB,
			$item->canStackWith(VanillaItems::SENTRY_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::SENTRY,
			$item->canStackWith(VanillaItems::SHAPER_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::SHAPER,
			$item->canStackWith(VanillaItems::SILENCE_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::SILENCE,
			$item->canStackWith(VanillaItems::SNOUT_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::SNOUT,
			$item->canStackWith(VanillaItems::SPIRE_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::SPIRE,
			$item->canStackWith(VanillaItems::TIDE_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::TIDE,
			$item->canStackWith(VanillaItems::VEX_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::VEX,
			$item->canStackWith(VanillaItems::WARD_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::WARD,
			$item->canStackWith(VanillaItems::WAYFINDER_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::WAYFINDER,
			$item->canStackWith(VanillaItems::WILD_ARMOR_TRIM_SMITHING_TEMPLATE()) => self::WILD,
			default => null,
		};
	}

	public static function tryFromStringId(string $id) : ?self{
		return match($id){
			"coast" => self::COAST,
			"dune" => self::DUNE,
			"eye" => self::EYE,
			"flow" => self::FLOW,
			"host" => self::HOST,
			"bolt" => self::BOLT,
			"raiser" => self::RAISER,
			"rib" => self::RIB,
			"sentry" => self::SENTRY,
			"shaper" => self::SHAPER,
			"silence" => self::SILENCE,
			"snout" => self::SNOUT,
			"spire" => self::SPIRE,
			"tide" => self::TIDE,
			"vex" => self::VEX,
			"ward" => self::WARD,
			"wayfinder" => self::WAYFINDER,
			"wild" => self::WILD,
			"minecraft:coast" => self::COAST,
			"minecraft:dune" => self::DUNE,
			"minecraft:eye" => self::EYE,
			"minecraft:flow" => self::FLOW,
			"minecraft:host" => self::HOST,
			"minecraft:bolt" => self::BOLT,
			"minecraft:raiser" => self::RAISER,
			"minecraft:rib" => self::RIB,
			"minecraft:sentry" => self::SENTRY,
			"minecraft:shaper" => self::SHAPER,
			"minecraft:silence" => self::SILENCE,
			"minecraft:snout" => self::SNOUT,
			"minecraft:spire" => self::SPIRE,
			"minecraft:tide" => self::TIDE,
			"minecraft:vex" => self::VEX,
			"minecraft:ward" => self::WARD,
			"minecraft:wayfinder" => self::WAYFINDER,
			"minecraft:wild" => self::WILD,
			"minecraft:coast_armor_trim_smithing_template" => self::COAST,
			"minecraft:dune_armor_trim_smithing_template" => self::DUNE,
			"minecraft:eye_armor_trim_smithing_template" => self::EYE,
			"minecraft:flow_armor_trim_smithing_template" => self::FLOW,
			"minecraft:host_armor_trim_smithing_template" => self::HOST,
			"minecraft:bolt_armor_trim_smithing_template" => self::BOLT,
			"minecraft:raiser_armor_trim_smithing_template" => self::RAISER,
			"minecraft:rib_armor_trim_smithing_template" => self::RIB,
			"minecraft:sentry_armor_trim_smithing_template" => self::SENTRY,
			"minecraft:shaper_armor_trim_smithing_template" => self::SHAPER,
			"minecraft:silence_armor_trim_smithing_template" => self::SILENCE,
			"minecraft:snout_armor_trim_smithing_template" => self::SNOUT,
			"minecraft:spire_armor_trim_smithing_template" => self::SPIRE,
			"minecraft:tide_armor_trim_smithing_template" => self::TIDE,
			"minecraft:vex_armor_trim_smithing_template" => self::VEX,
			"minecraft:ward_armor_trim_smithing_template" => self::WARD,
			"minecraft:wayfinder_armor_trim_smithing_template" => self::WAYFINDER,
			"minecraft:wild_armor_trim_smithing_template" => self::WILD,
			default => null,
		};
	}
}
