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

use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\entity\object\ArmorStand as EntityArmorStand;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ArmorStandSound;
use pocketmine\world\sound\ArmorStandSoundType;
use function fmod;
use function round;

class ArmorStand extends Item{

	public function getMaxStackSize() : int{
		return 16;
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if(!$blockClicked->isSolid() || !$blockReplace->canBeReplaced()){
			return ItemUseResult::NONE;
		}

		$world = $blockClicked->getPosition()->getWorld();
		$spawnPos = $blockReplace->getPosition()->add(0.5, 0.0, 0.5);

		$halfWidth = 0.25;
		$collisionBox = new AxisAlignedBB(
			$spawnPos->x - $halfWidth,
			$spawnPos->y,
			$spawnPos->z - $halfWidth,
			$spawnPos->x + $halfWidth,
			$spawnPos->y + 1.975,
			$spawnPos->z + $halfWidth
		);

		foreach($world->getNearbyEntities($collisionBox) as $entity){
			if($entity instanceof EntityArmorStand){
				return ItemUseResult::NONE;
			}
		}

		$yaw = fmod($player->getLocation()->getYaw() + 180, 360);
		$yaw = round($yaw / 45) * 45;

		$armorStand = new EntityArmorStand(Location::fromObject($spawnPos, $world, $yaw, 0.0));
		$armorStand->spawnToAll();

		$world->addSound($spawnPos, new ArmorStandSound(ArmorStandSoundType::PLACE));

		if($player->hasFiniteResources()){
			$this->pop();
		}
		return ItemUseResult::SUCCESS;
	}
}
