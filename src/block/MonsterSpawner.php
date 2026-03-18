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
namespace pocketmine\block;

use pocketmine\block\tile\MonsterSpawner as TileMonsterSpawner;
use pocketmine\block\utils\SupportType;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityFactory;
use pocketmine\item\Item;
use pocketmine\item\SpawnEgg;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\world\World;
use function mt_rand;

class MonsterSpawner extends Transparent{

	public function getDropsForCompatibleTool(Item $item) : array{
		return [];
	}

	protected function getXpDropAmount() : int{
		return mt_rand(15, 43);
	}

	public function onScheduledUpdate() : void{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if(!$tile instanceof TileMonsterSpawner){
			$world->scheduleDelayedBlockUpdate($this->position, 20);
			return;
		}

		$entityTypeId = $tile->getEntityTypeId();
		if($entityTypeId === '' || $entityTypeId === ':'){
			$world->scheduleDelayedBlockUpdate($this->position, 100);
			return;
		}

		$delay = $tile->getSpawnDelay();
		$tile->setSpawnDelay($delay - 1);
		if($delay > 1){
			$world->scheduleDelayedBlockUpdate($this->position, 1);
			return;
		}

		$pos = $this->position;
		$range = TileMonsterSpawner::DEFAULT_REQUIRED_PLAYER_RANGE;
		$hasPlayer = false;
		foreach($world->getPlayers() as $player){
			if($player->getPosition()->distance($pos) <= $range){
				$hasPlayer = true;
				break;
			}
		}
		if(!$hasPlayer){
			$tile->setSpawnDelay(mt_rand(TileMonsterSpawner::DEFAULT_MIN_SPAWN_DELAY, TileMonsterSpawner::DEFAULT_MAX_SPAWN_DELAY));
			$world->scheduleDelayedBlockUpdate($this->position, 100);
			return;
		}

		$nearby = 0;
		$spawnRange = TileMonsterSpawner::DEFAULT_SPAWN_RANGE;
		$maxNearby = TileMonsterSpawner::DEFAULT_MAX_NEARBY_ENTITIES;
		$factory = EntityFactory::getInstance();
		foreach($world->getEntities() as $entity){
			if($entity->getPosition()->distance($pos) > $spawnRange * 2){
				continue;
			}
			try{
				if($factory->getSaveId($entity::class) === $entityTypeId){
					$nearby++;
				}
			}catch(\InvalidArgumentException){
				// entity type not in factory
			}
		}
		if($nearby >= $maxNearby){
			$tile->setSpawnDelay(mt_rand(TileMonsterSpawner::DEFAULT_MIN_SPAWN_DELAY, TileMonsterSpawner::DEFAULT_MAX_SPAWN_DELAY));
			$world->scheduleDelayedBlockUpdate($this->position, 20);
			return;
		}

		$dx = (mt_rand() / mt_getrandmax() * 2 - 1) * $spawnRange;
		$dz = (mt_rand() / mt_getrandmax() * 2 - 1) * $spawnRange;
		$spawnPos = $pos->add(0.5 + $dx, 1, 0.5 + $dz);

		$nbt = CompoundTag::create()
			->setString(EntityFactory::TAG_IDENTIFIER, $entityTypeId)
			->setTag(Entity::TAG_POS, new ListTag([
				new DoubleTag($spawnPos->x),
				new DoubleTag($spawnPos->y),
				new DoubleTag($spawnPos->z)
			]))
			->setTag(Entity::TAG_ROTATION, new ListTag([
				new FloatTag((mt_rand() / mt_getrandmax()) * 360),
				new FloatTag(0.0)
			]));
		$entity = EntityFactory::getInstance()->createFromData($world, $nbt);
		// Entity constructor already adds itself to world via addEntity($this)
		if($entity !== null){
			$entity->spawnToAll();
		}

		$tile->setSpawnDelay(mt_rand(TileMonsterSpawner::DEFAULT_MIN_SPAWN_DELAY, TileMonsterSpawner::DEFAULT_MAX_SPAWN_DELAY));
		$world->scheduleDelayedBlockUpdate($this->position, 20);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player !== null && $item instanceof SpawnEgg){
			$world = $this->position->getWorld();
			$tile = $world->getTile($this->position);
			if($tile instanceof TileMonsterSpawner){
				$entity = $item->createEntity($world, $this->position->add(0.5, 0, 0.5), 0.0, 0.0);
				try{
					$entityTypeId = EntityFactory::getInstance()->getSaveId($entity::class);
					$tile->setEntityTypeId($entityTypeId);
					$tile->setSpawnDelay(TileMonsterSpawner::DEFAULT_MIN_SPAWN_DELAY);
					$tile->setDirty();
					$world->scheduleDelayedBlockUpdate($this->position, 1);
				}catch(\InvalidArgumentException){
				}finally{
					$entity->close();
				}
				$item->pop();
				$player->getInventory()->setItemInHand($item);
				return true;
			}
		}
		return parent::onInteract($item, $face, $clickVector, $player, $returnedItems);
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}
}
