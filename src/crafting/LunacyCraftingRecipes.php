<?php

declare(strict_types=1);

namespace pocketmine\crafting;

use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;

final class LunacyCraftingRecipes{

	private function __construct(){
	}

	public static function register(CraftingManager $manager) : void{
		$manager->registerShapedRecipe(new ShapedRecipe(
			["PPP", "CIC", "CRC"],
			[
				"P" => new TagWildcardRecipeIngredient("minecraft:planks"),
				"C" => new ExactRecipeIngredient(VanillaBlocks::COBBLESTONE()->asItem()),
				"I" => new ExactRecipeIngredient(VanillaItems::IRON_INGOT()),
				"R" => new ExactRecipeIngredient(VanillaItems::REDSTONE_DUST()),
			],
			[VanillaBlocks::PISTON()->asItem()]
		));

		$manager->registerShapelessRecipe(new ShapelessRecipe(
			[
				new ExactRecipeIngredient(VanillaItems::SLIMEBALL()),
				new ExactRecipeIngredient(VanillaBlocks::PISTON()->asItem()),
			],
			[VanillaBlocks::STICKY_PISTON()->asItem()],
			ShapelessRecipeType::CRAFTING
		));

		$manager->registerShapedRecipe(new ShapedRecipe(
			["AAA", " A ", "ABA"],
			[
				"A" => new ExactRecipeIngredient(VanillaItems::STICK()),
				"B" => new ExactRecipeIngredient(VanillaBlocks::SMOOTH_STONE_SLAB()->asItem()),
			],
			[VanillaItems::ARMOR_STAND()]
		));
	}
}
