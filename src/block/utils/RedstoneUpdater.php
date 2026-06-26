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
use pocketmine\block\Button;
use pocketmine\block\Lever;
use pocketmine\block\PistonBase;
use pocketmine\block\Redstone;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

final class RedstoneUpdater{

	private static int $depth = 0;
	private const MAX_DEPTH = 32;

	private function __construct(){
	}

	public static function notifyAround(Block $source) : void{
		$pos = $source->getPosition();
		$world = $pos->getWorld();
		foreach(Facing::ALL as $face){
			self::updateAt($world, $pos->getSide($face));
		}
	}

	public static function updateAt(World $world, Vector3 $pos) : void{
		if(self::$depth >= self::MAX_DEPTH){
			return;
		}

		++self::$depth;
		try{
			$block = $world->getBlock($pos);
			if($block instanceof RedstoneWire){
				self::updateWire($block);
			}elseif($block instanceof PistonBase){
				$world->scheduleDelayedBlockUpdate($block->getPosition(), 1);
			}
		}finally{
			--self::$depth;
		}
	}

	public static function updateWire(RedstoneWire $wire) : void{
		$pos = $wire->getPosition();
		$world = $pos->getWorld();
		$newPower = self::calculateWirePower($pos, $world);

		if($wire->getOutputSignalStrength() !== $newPower){
			$world->setBlock($pos, (clone $wire)->setOutputSignalStrength($newPower));
			foreach(Facing::ALL as $face){
				self::updateAt($world, $pos->getSide($face));
			}
		}
	}

	public static function calculateWirePower(Position $pos, World $world) : int{
		$max = 0;

		foreach(Facing::ALL as $face){
			$sideBlock = $world->getBlock($pos->getSide($face));
			if($sideBlock instanceof RedstoneWire){
				$max = max($max, $sideBlock->getOutputSignalStrength() - 1);
			}else{
				$max = max($max, self::getBlockPowerToward($sideBlock, Facing::opposite($face)));
			}
		}

		return max(0, min(15, $max));
	}

	public static function getBlockPowerToward(Block $block, int $towardFace) : int{
		if($block instanceof Redstone){
			return 15;
		}

		if($block instanceof RedstoneTorch && $block->isLit()){
			return 15;
		}

		if($block instanceof Lever && $block->isActivated()){
			return 15;
		}

		if($block instanceof Button && $block->isPressed()){
			return 15;
		}

		if($block instanceof PoweredByRedstone && $block->isPowered()){
			return 15;
		}

		if($block instanceof AnalogRedstoneSignalEmitter){
			return $block->getOutputSignalStrength();
		}

		return 0;
	}
}
