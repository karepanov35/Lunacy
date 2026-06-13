<?php

declare(strict_types=1);

namespace pocketmine\block\utils;

use pocketmine\block\Block;
use pocketmine\block\Button;
use pocketmine\block\Lever;
use pocketmine\block\PistonBase;
use pocketmine\block\Redstone;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\block\SimplePressurePlate;
use pocketmine\math\Facing;

final class RedstonePowerHelper{

	private function __construct(){
	}

	public static function isDirectlyPowered(Block $block) : bool{
		$pos = $block->getPosition();
		$world = $pos->getWorld();
		$excludeFace = $block instanceof PistonBase ? $block->getFacing() : null;

		foreach(Facing::ALL as $face){
			if($excludeFace !== null && $face === $excludeFace){
				continue;
			}
			if(self::emitsPowerToward($world->getBlock($pos->getSide($face)), Facing::opposite($face))){
				return true;
			}
		}

		$above = $world->getBlock($pos->up());
		if($above instanceof RedstoneWire && $above->getOutputSignalStrength() > 0){
			return true;
		}

		return false;
	}

	private static function emitsPowerToward(Block $block, int $towardFace) : bool{
		if($block instanceof Redstone){
			return true;
		}

		if($block instanceof PoweredByRedstone && $block->isPowered()){
			return true;
		}

		if($block instanceof AnalogRedstoneSignalEmitter && $block->getOutputSignalStrength() > 0){
			return true;
		}

		if($block instanceof RedstoneTorch && $block->isLit()){
			return true;
		}

		if($block instanceof Button && $block->isPressed()){
			return true;
		}

		if($block instanceof Lever && $block->isActivated()){
			return true;
		}

		if($block instanceof SimplePressurePlate && $block->isPressed()){
			return true;
		}

		if($block instanceof RedstoneWire && $block->getOutputSignalStrength() > 0){
			return true;
		}

		return false;
	}
}
