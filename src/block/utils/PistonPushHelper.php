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

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Door;
use pocketmine\block\EndPortalFrame;
use pocketmine\block\Flowable;
use pocketmine\block\Liquid;
use pocketmine\block\PistonArmCollision;
use pocketmine\block\PistonBase;
use pocketmine\block\Rail;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\Tile;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class PistonPushHelper{

	public const MAX_PUSH_BLOCKS = 12;

	public static function blockKey(Vector3 $pos) : string{
		return $pos->getFloorX() . ":" . $pos->getFloorY() . ":" . $pos->getFloorZ();
	}

	public static function sortForPush(array $blocks, int $direction) : array{
		if(count($blocks) < 2){
			return $blocks;
		}

		$axis = Facing::axis($direction);
		$factor = Facing::isPositive($direction) ? -1 : 1;

		usort($blocks, static function(Vector3 $a, Vector3 $b) use ($axis, $factor) : int{
			$av = match($axis){
				Axis::X => $a->x,
				Axis::Y => $a->y,
				Axis::Z => $a->z,
			};
			$bv = match($axis){
				Axis::X => $b->x,
				Axis::Y => $b->y,
				Axis::Z => $b->z,
			};
			return ($av <=> $bv) * $factor;
		});

		return $blocks;
	}

	public static function canPushBlock(Block $block, int $moveDirection, bool $destroyBlocks, bool $extending, Vector3 $pistonPos, World $world) : bool{
		$pos = $block->getPosition();
		$minY = $world->getMinY();
		$maxY = $world->getMaxY();

		if($pos->y < $minY || $pos->y > $maxY){
			return false;
		}
		if($moveDirection === Facing::DOWN && $pos->y <= $minY){
			return false;
		}
		if($moveDirection === Facing::UP && $pos->y >= $maxY){
			return false;
		}

		if($extending && !self::canBePushed($block)){
			return false;
		}
		if(!$extending && !self::canBePulled($block)){
			return false;
		}

		if(self::breaksWhenMoved($block)){
			return $destroyBlocks || self::sticksToPiston($block);
		}

		$tile = $world->getTile($pos);
		if($tile !== null && !self::isTileMovable($tile)){
			return false;
		}

		return !self::sameBlock($pos, $pistonPos);
	}

	public static function canBePushed(Block $block) : bool{
		if($block instanceof PistonBase || $block instanceof PistonArmCollision){
			return false;
		}

		return !self::isImmovable($block);
	}

	public static function canBePulled(Block $block) : bool{
		return self::canBePushed($block);
	}

	public static function breaksWhenMoved(Block $block) : bool{
		if($block instanceof Liquid){
			return true;
		}
		if($block instanceof Flowable && !$block instanceof Rail){
			return true;
		}
		if($block instanceof Door){
			return true;
		}

		return match($block->getTypeId()){
			BlockTypeIds::BED,
			BlockTypeIds::CAKE,
			BlockTypeIds::CACTUS,
			BlockTypeIds::CHORUS_FLOWER,
			BlockTypeIds::CHORUS_PLANT,
			BlockTypeIds::COCOA_POD,
			BlockTypeIds::DRAGON_EGG,
			BlockTypeIds::MELON,
			BlockTypeIds::PUMPKIN,
			BlockTypeIds::CARVED_PUMPKIN,
			BlockTypeIds::LIT_PUMPKIN,
			BlockTypeIds::SNOW_LAYER,
			BlockTypeIds::SHULKER_BOX,
			BlockTypeIds::ITEM_FRAME,
			BlockTypeIds::GLOWING_ITEM_FRAME,
			BlockTypeIds::BANNER,
			BlockTypeIds::WALL_BANNER,
			BlockTypeIds::LADDER,
			BlockTypeIds::VINES,
			BlockTypeIds::TRIPWIRE,
			BlockTypeIds::TRIPWIRE_HOOK,
			BlockTypeIds::SUGARCANE,
			BlockTypeIds::BAMBOO,
			BlockTypeIds::BAMBOO_SAPLING,
			BlockTypeIds::DEAD_BUSH,
			BlockTypeIds::TALL_GRASS,
			BlockTypeIds::FERN,
			BlockTypeIds::LARGE_FERN,
			BlockTypeIds::SUNFLOWER,
			BlockTypeIds::LILAC,
			BlockTypeIds::ROSE_BUSH,
			BlockTypeIds::PEONY,
			BlockTypeIds::WITHER_ROSE,
			BlockTypeIds::SWEET_BERRY_BUSH,
			BlockTypeIds::NETHER_WART,
			BlockTypeIds::FLOWER_POT,
			BlockTypeIds::CARPET,
			BlockTypeIds::MOSS_CARPET,
			BlockTypeIds::AMETHYST_CLUSTER,
			=> true,
			default => false,
		};
	}

	public static function sticksToPiston(Block $block) : bool{
		return !($block instanceof Liquid);
	}

	public static function sameBlock(Vector3 $a, Vector3 $b) : bool{
		return $a->getFloorX() === $b->getFloorX()
			&& $a->getFloorY() === $b->getFloorY()
			&& $a->getFloorZ() === $b->getFloorZ();
	}

	public static function destroyBlockAt(World $world, Vector3 $pos) : void{
		$item = null;
		$returnedItems = [];
		$world->useBreakOn($pos, $item, null, true, $returnedItems);
	}

	private static function isImmovable(Block $block) : bool{
		if($block instanceof PistonBase || $block instanceof PistonArmCollision){
			return true;
		}

		return match($block->getTypeId()){
			BlockTypeIds::OBSIDIAN,
			BlockTypeIds::CRYING_OBSIDIAN,
			BlockTypeIds::GLOWING_OBSIDIAN,
			BlockTypeIds::BEDROCK,
			BlockTypeIds::INVISIBLE_BEDROCK,
			BlockTypeIds::BARRIER,
			BlockTypeIds::BEACON,
			BlockTypeIds::MONSTER_SPAWNER,
			BlockTypeIds::END_PORTAL,
			BlockTypeIds::END_PORTAL_FRAME,
			BlockTypeIds::NETHER_PORTAL,
			BlockTypeIds::STRUCTURE_VOID,
			BlockTypeIds::ENDER_CHEST,
			BlockTypeIds::RESPAWN_ANCHOR,
			BlockTypeIds::JUKEBOX,
			BlockTypeIds::DAYLIGHT_SENSOR,
			BlockTypeIds::ENCHANTING_TABLE,
			BlockTypeIds::REINFORCED_DEEPSLATE,
			BlockTypeIds::NETHER_REACTOR_CORE,
			=> true,
			default => $block instanceof EndPortalFrame,
		};
	}

	private static function isTileMovable(Tile $tile) : bool{
		if($tile instanceof Container){
			foreach($tile->getInventory()->getViewers() as $_){
				return false;
			}
		}
		return true;
	}
}
