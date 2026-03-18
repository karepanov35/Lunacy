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
namespace pocketmine\item\utils;

use pocketmine\block\VanillaBlocks;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\TieredTool;
use pocketmine\item\ToolTier;
use pocketmine\item\VanillaItems;

final class ItemRepairUtils{
	private function __construct(){
		//NOOP
	}

	public static function isRepairableWith(Durable $target, Item $sacrifice) : bool{
		$validRepairMaterials = self::getRepairMaterial($target);
		if($validRepairMaterials === null){
			return false;
		}
		foreach($validRepairMaterials as $material){
			if($material->equals($sacrifice, false, false)){
				return true;
			}
		}
		return false;
	}

	/**
	 * This method determines what item should be used to repair tools, armor, etc.
	 *
	 * @return Item[]|null
	 */
	public static function getRepairMaterial(Durable $target) : ?array{
		if($target instanceof TieredTool){
			return match ($target->getTier()) {
				ToolTier::WOOD => [VanillaBlocks::OAK_PLANKS()->asItem()],
				ToolTier::STONE => [VanillaBlocks::COBBLESTONE()->asItem()],
				ToolTier::GOLD => [VanillaItems::GOLD_INGOT()],
				ToolTier::IRON => [VanillaItems::IRON_INGOT()],
				ToolTier::DIAMOND => [VanillaItems::DIAMOND()],
				ToolTier::NETHERITE => [VanillaItems::NETHERITE_INGOT()],
				default => null
			};
		}
		return match ($target->getTypeId()) {
			ItemTypeIds::LEATHER_CAP,
			ItemTypeIds::LEATHER_TUNIC,
			ItemTypeIds::LEATHER_PANTS,
			ItemTypeIds::LEATHER_BOOTS => [VanillaItems::LEATHER()],

			ItemTypeIds::IRON_HELMET,
			ItemTypeIds::IRON_CHESTPLATE,
			ItemTypeIds::IRON_LEGGINGS,
			ItemTypeIds::IRON_BOOTS,
			ItemTypeIds::CHAINMAIL_HELMET,
			ItemTypeIds::CHAINMAIL_CHESTPLATE,
			ItemTypeIds::CHAINMAIL_LEGGINGS,
			ItemTypeIds::CHAINMAIL_BOOTS => [VanillaItems::IRON_INGOT()],

			ItemTypeIds::GOLDEN_HELMET,
			ItemTypeIds::GOLDEN_CHESTPLATE,
			ItemTypeIds::GOLDEN_LEGGINGS,
			ItemTypeIds::GOLDEN_BOOTS => [VanillaItems::GOLD_INGOT()],

			ItemTypeIds::DIAMOND_HELMET,
			ItemTypeIds::DIAMOND_CHESTPLATE,
			ItemTypeIds::DIAMOND_LEGGINGS,
			ItemTypeIds::DIAMOND_BOOTS => [VanillaItems::DIAMOND()],

			ItemTypeIds::NETHERITE_HELMET,
			ItemTypeIds::NETHERITE_CHESTPLATE,
			ItemTypeIds::NETHERITE_LEGGINGS,
			ItemTypeIds::NETHERITE_BOOTS => [VanillaItems::NETHERITE_INGOT()],

			ItemTypeIds::ELYTRA => [VanillaItems::PHANTOM_MEMBRANE()],
			ItemTypeIds::TURTLE_HELMET => [VanillaItems::SCUTE()],
			ItemTypeIds::SHIELD => [
				VanillaBlocks::OAK_PLANKS()->asItem(),
				VanillaBlocks::BIRCH_PLANKS()->asItem(),
				VanillaBlocks::ACACIA_PLANKS()->asItem(),
				VanillaBlocks::DARK_OAK_PLANKS()->asItem(),
				VanillaBlocks::JUNGLE_PLANKS()->asItem(),
				VanillaBlocks::SPRUCE_PLANKS()->asItem(),
			],
			default => null
		};
	}
}
