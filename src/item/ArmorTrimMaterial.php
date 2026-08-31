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
 * @link https://github.com/MaJiHoBou999/Lunacy
 *
 *
 */

declare(strict_types=1);
namespace pocketmine\item;

use pocketmine\item\VanillaItems;
use pocketmine\utils\LegacyEnumShimTrait;

/**
 * @method static ArmorTrimMaterial AMETHYST()
 * @method static ArmorTrimMaterial COPPER()
 * @method static ArmorTrimMaterial DIAMOND()
 * @method static ArmorTrimMaterial EMERALD()
 * @method static ArmorTrimMaterial GOLD()
 * @method static ArmorTrimMaterial IRON()
 * @method static ArmorTrimMaterial LAPIS()
 * @method static ArmorTrimMaterial NETHERITE()
 * @method static ArmorTrimMaterial QUARTZ()
 * @method static ArmorTrimMaterial REDSTONE()
 * @method static ArmorTrimMaterial RESIN()
 */
enum ArmorTrimMaterial{
	use LegacyEnumShimTrait;

	case AMETHYST;
	case COPPER;
	case DIAMOND;
	case EMERALD;
	case GOLD;
	case IRON;
	case LAPIS;
	case NETHERITE;
	case QUARTZ;
	case REDSTONE;
	case RESIN;

	public function getMaterialId() : string{
		return match($this){
			self::AMETHYST => "amethyst",
			self::COPPER => "copper",
			self::DIAMOND => "diamond",
			self::EMERALD => "emerald",
			self::GOLD => "gold",
			self::IRON => "iron",
			self::LAPIS => "lapis",
			self::NETHERITE => "netherite",
			self::QUARTZ => "quartz",
			self::REDSTONE => "redstone",
			self::RESIN => "resin",
		};
	}

	public function getColorId() : string{
		return match($this){
			self::AMETHYST => "\u{00A7}u",
			self::COPPER => "\u{00A7}n",
			self::DIAMOND => "\u{00A7}s",
			self::EMERALD => "\u{00A7}q",
			self::GOLD => "\u{00A7}p",
			self::IRON => "\u{00A7}i",
			self::LAPIS => "\u{00A7}t",
			self::NETHERITE => "\u{00A7}j",
			self::QUARTZ => "\u{00A7}h",
			self::REDSTONE => "\u{00A7}m",
			self::RESIN => "\u{00A7}v",
		};
	}

	public function getItemId() : string{
		return match($this){
			self::AMETHYST => "minecraft:amethyst_shard",
			self::COPPER => "minecraft:copper_ingot",
			self::DIAMOND => "minecraft:diamond",
			self::EMERALD => "minecraft:emerald",
			self::GOLD => "minecraft:gold_ingot",
			self::IRON => "minecraft:iron_ingot",
			self::LAPIS => "minecraft:lapis_lazuli",
			self::NETHERITE => "minecraft:netherite_ingot",
			self::QUARTZ => "minecraft:quartz",
			self::REDSTONE => "minecraft:redstone",
			self::RESIN => "minecraft:resin_brick",
		};
	}

	public static function tryFromItem(Item $item) : ?self{
		return match(true){
			$item->canStackWith(VanillaItems::AMETHYST_SHARD()) => self::AMETHYST,
			$item->canStackWith(VanillaItems::COPPER_INGOT()) => self::COPPER,
			$item->canStackWith(VanillaItems::DIAMOND()) => self::DIAMOND,
			$item->canStackWith(VanillaItems::EMERALD()) => self::EMERALD,
			$item->canStackWith(VanillaItems::GOLD_INGOT()) => self::GOLD,
			$item->canStackWith(VanillaItems::IRON_INGOT()) => self::IRON,
			$item->canStackWith(VanillaItems::LAPIS_LAZULI()) => self::LAPIS,
			$item->canStackWith(VanillaItems::NETHERITE_INGOT()) => self::NETHERITE,
			$item->canStackWith(VanillaItems::NETHER_QUARTZ()) => self::QUARTZ,
			$item->canStackWith(VanillaItems::REDSTONE_DUST()) => self::REDSTONE,
			$item->canStackWith(VanillaItems::RESIN_BRICK()) => self::RESIN,
			default => null,
		};
	}

	public static function tryFromStringId(string $id) : ?self{
		return match($id){
			"amethyst" => self::AMETHYST,
			"copper" => self::COPPER,
			"diamond" => self::DIAMOND,
			"emerald" => self::EMERALD,
			"gold" => self::GOLD,
			"iron" => self::IRON,
			"lapis" => self::LAPIS,
			"netherite" => self::NETHERITE,
			"quartz" => self::QUARTZ,
			"redstone" => self::REDSTONE,
			"resin" => self::RESIN,
			"minecraft:amethyst" => self::AMETHYST,
			"minecraft:copper" => self::COPPER,
			"minecraft:diamond" => self::DIAMOND,
			"minecraft:emerald" => self::EMERALD,
			"minecraft:gold" => self::GOLD,
			"minecraft:iron" => self::IRON,
			"minecraft:lapis" => self::LAPIS,
			"minecraft:netherite" => self::NETHERITE,
			"minecraft:quartz" => self::QUARTZ,
			"minecraft:redstone" => self::REDSTONE,
			"minecraft:resin" => self::RESIN,
			"minecraft:amethyst_shard" => self::AMETHYST,
			"minecraft:copper_ingot" => self::COPPER,
			"minecraft:diamond" => self::DIAMOND,
			"minecraft:emerald" => self::EMERALD,
			"minecraft:gold_ingot" => self::GOLD,
			"minecraft:iron_ingot" => self::IRON,
			"minecraft:lapis_lazuli" => self::LAPIS,
			"minecraft:netherite_ingot" => self::NETHERITE,
			"minecraft:quartz" => self::QUARTZ,
			"minecraft:redstone" => self::REDSTONE,
			"minecraft:resin_brick" => self::RESIN,
			default => null,
		};
	}
}
