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
namespace pocketmine\item;

use pocketmine\block\Liquid;
use pocketmine\entity\Living;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\world\sound\ChorusFruitTeleportSound;
use pocketmine\world\World;
use function floor;
use function lcg_value;
use function max;
use function min;
use function mt_rand;

class ChorusFruit extends Food{

	public function getFoodRestore() : int{
		return 4;
	}

	public function getSaturationRestore() : float{
		return 2.4;
	}

	public function requiresHunger() : bool{
		return false;
	}

	public function onConsume(Living $consumer) : void{
		$world = $consumer->getWorld();
		$origin = $consumer->getPosition();

		$minY = $world->getMinY();
		$maxY = $world->getMaxY() - 1;

		for($attempts = 0; $attempts < 16; ++$attempts){
			$targetX = $origin->x + (lcg_value() - 0.5) * 16.0;
			$targetZ = $origin->z + (lcg_value() - 0.5) * 16.0;

			if($consumer->isGliding()){
				$targetY = $this->findSurfaceY($world, $targetX, $targetZ);
			}else{
				$randomY = max($minY, min($maxY, $origin->y + mt_rand(0, 15) - 8));
				$targetY = $this->findTeleportY($world, $targetX, $randomY, $targetZ);
			}

			if($targetY === null){
				continue;
			}

			$target = new Vector3($targetX, $targetY, $targetZ);
			if(!$this->canFitAt($consumer, $target)){
				continue;
			}

			if($consumer->teleport($target)){
				$world->addSound($origin, new ChorusFruitTeleportSound());
			}

			break;
		}
	}

	private function findTeleportY(World $world, float $x, float $y, float $z) : ?float{
		$minY = $world->getMinY();
		$blockY = (int) floor($y);

		while($blockY > $minY){
			$below = $world->getBlockAt((int) floor($x), $blockY - 1, (int) floor($z));
			if($below->isSolid()){
				return (float) $blockY;
			}
			$blockY--;
		}

		$below = $world->getBlockAt((int) floor($x), $minY, (int) floor($z));
		if($below->isSolid()){
			return (float) ($minY + 1);
		}

		return null;
	}

	private function findSurfaceY(World $world, float $x, float $z) : ?float{
		$blockX = (int) floor($x);
		$blockZ = (int) floor($z);

		$highestBlock = $world->getChunk($blockX >> 4, $blockZ >> 4)?->getHighestBlockAt($blockX & 0x0f, $blockZ & 0x0f);
		if($highestBlock === null){
			return null;
		}

		for($y = $highestBlock; $y >= $world->getMinY(); --$y){
			$block = $world->getBlockAt($blockX, $y, $blockZ);
			if($block->isSolid()){
				return (float) ($y + 1);
			}
		}

		return null;
	}

	private function canFitAt(Living $entity, Vector3 $pos) : bool{
		$world = $entity->getWorld();
		$halfWidth = $entity->getSize()->getWidth() / 2;
		$height = $entity->getSize()->getHeight();

		$bb = new AxisAlignedBB(
			$pos->x - $halfWidth,
			$pos->y,
			$pos->z - $halfWidth,
			$pos->x + $halfWidth,
			$pos->y + $height,
			$pos->z + $halfWidth
		);

		if(count($world->getBlockCollisionBoxes($bb)) > 0){
			return false;
		}

		$minBlockX = (int) floor($bb->minX);
		$maxBlockX = (int) floor($bb->maxX);
		$minBlockY = (int) floor($bb->minY);
		$maxBlockY = (int) floor($bb->maxY);
		$minBlockZ = (int) floor($bb->minZ);
		$maxBlockZ = (int) floor($bb->maxZ);

		for($x = $minBlockX; $x <= $maxBlockX; ++$x){
			for($y = $minBlockY; $y <= $maxBlockY; ++$y){
				for($z = $minBlockZ; $z <= $maxBlockZ; ++$z){
					$block = $world->getBlockAt($x, $y, $z);
					if($block instanceof Liquid){
						return false;
					}
				}
			}
		}

		return true;
	}

	public function getCooldownTicks() : int{
		return 20;
	}

	public function getCooldownTag() : ?string{
		return ItemCooldownTags::CHORUS_FRUIT;
	}
}
