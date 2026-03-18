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
use pocketmine\block\MonsterSpawner as BlockMonsterSpawner;
use pocketmine\block\tile\MonsterSpawner as TileMonsterSpawner;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Utils;
use pocketmine\world\World;

abstract class SpawnEgg extends Item{

	abstract public function createEntity(World $world, Vector3 $pos, float $yaw, float $pitch) : Entity;

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if($blockClicked instanceof BlockMonsterSpawner){
			$world = $blockClicked->getPosition()->getWorld();
			$tile = $world->getTile($blockClicked->getPosition());
			if($tile instanceof TileMonsterSpawner){
				$entity = $this->createEntity($world, $blockClicked->getPosition()->add(0.5, 0, 0.5), 0.0, 0.0);
				try{
					$entityTypeId = EntityFactory::getInstance()->getSaveId($entity::class);
					$tile->setEntityTypeId($entityTypeId);
					$tile->setSpawnDelay(TileMonsterSpawner::DEFAULT_MIN_SPAWN_DELAY);
					$world->scheduleDelayedBlockUpdate($blockClicked->getPosition(), 1);
				}catch(\InvalidArgumentException){
				}finally{
					$entity->close();
				}
				$this->pop();
				return ItemUseResult::SUCCESS;
			}
		}

		$entity = $this->createEntity($player->getWorld(), $blockReplace->getPosition()->add(0.5, 0, 0.5), Utils::getRandomFloat() * 360, 0);

		if($this->hasCustomName()){
			$entity->setNameTag($this->getCustomName());
		}
		$this->pop();
		$entity->spawnToAll();
		//TODO: what if the entity was marked for deletion?
		return ItemUseResult::SUCCESS;
	}
}
