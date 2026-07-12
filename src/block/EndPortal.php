<?php


/*
 *
 *
 *тЦТтЦИтЦСтЦСтЦС тЦТтЦИтЦСтЦТтЦИ тЦТтЦИтЦДтЦСтЦТтЦИ тЦСтЦИтЦАтЦАтЦИ тЦТтЦИтЦАтЦАтЦИ тЦТтЦИтЦСтЦСтЦТтЦИ
 *тЦТтЦИтЦСтЦСтЦС тЦТтЦИтЦСтЦТтЦИ тЦТтЦИтЦТтЦИтЦТтЦИ тЦТтЦИтЦДтЦДтЦИ тЦТтЦИтЦСтЦСтЦС тЦТтЦИтЦДтЦДтЦДтЦИ
 *тЦТтЦИтЦДтЦДтЦИ тЦСтЦАтЦДтЦДтЦА тЦТтЦИтЦСтЦСтЦАтЦИ тЦТтЦИтЦСтЦТтЦИ тЦТтЦИтЦДтЦДтЦИ тЦСтЦСтЦТтЦИтЦСтЦС
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

use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\cache\ChunkCache;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\end\TheEndGenerator;
use pocketmine\world\generator\overworld\OverworldGenerator;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\sound\EndermanTeleportSound;
use pocketmine\world\sound\EndPortalSpawnSound;

class EndPortal extends Transparent{

	/** @var array<int, int> */
	private static array $teleportCooldown = [];

	/** @var array<int, int> */
	private static array $crossDimensionPortalImmuneUntil = [];

	private const TELEPORT_COOLDOWN_TICKS = 100;
	private const CROSS_DIMENSION_PORTAL_IMMUNITY_TICKS = 400;
	private const OVERWORLD_WORLD_NAME = "world";
	private const THE_END_WORLD_NAME = "the_end";

	/** @var list<array{int, int, int}> [lx, lz, expectedFacing] */
	private const FRAME_LAYOUT = [
		[0, 1, Facing::EAST], [0, 2, Facing::EAST], [0, 3, Facing::EAST],
		[4, 1, Facing::WEST], [4, 2, Facing::WEST], [4, 3, Facing::WEST],
		[1, 0, Facing::SOUTH], [2, 0, Facing::SOUTH], [3, 0, Facing::SOUTH],
		[1, 4, Facing::NORTH], [2, 4, Facing::NORTH], [3, 4, Facing::NORTH],
	];

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
	}

	public function getLightLevel() : int{
		return 15;
	}

	public function isSolid() : bool{
		return false;
	}

	protected function recalculateCollisionBoxes() : array{
		return [];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function getDrops(Item $item) : array{
		return [];
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onEntityInside(Entity $entity) : bool{
		if(!($entity instanceof Living)){
			return true;
		}

		$world = $entity->getWorld();
		$server = $world->getServer();
		$currentTick = $server->getTick();
		$entityId = $entity->getId();

		if($currentTick < (self::$crossDimensionPortalImmuneUntil[$entityId] ?? 0)){
			return true;
		}
		if($currentTick < (self::$teleportCooldown[$entityId] ?? -PHP_INT_MAX)){
			return true;
		}
		self::$teleportCooldown[$entityId] = $currentTick + self::TELEPORT_COOLDOWN_TICKS;

		$sourceDim = ChunkCache::getDimensionIdForWorld($world);
		$destWorldName = $sourceDim === DimensionIds::THE_END ? self::OVERWORLD_WORLD_NAME : self::THE_END_WORLD_NAME;

		$worldManager = $server->getWorldManager();
		$destWorld = $worldManager->getWorldByName($destWorldName);
		if($destWorld === null){
			$options = WorldCreationOptions::create()
				->setSeed($world->getSeed())
				->setDifficulty($world->getDifficulty());
			if($destWorldName === self::THE_END_WORLD_NAME){
				$options->setGeneratorClass(TheEndGenerator::class)->setSpawnPosition(new Vector3(100, 49, 0));
			}else{
				$options->setGeneratorClass(OverworldGenerator::class);
			}
			if($worldManager->isWorldGenerated($destWorldName)){
				$worldManager->loadWorld($destWorldName);
			}else{
				$worldManager->generateWorld($destWorldName, $options, true);
			}
			$destWorld = $worldManager->getWorldByName($destWorldName);
		}

		if($destWorld === null){
			return true;
		}

		$pos = $entity->getPosition();
		$spawn = $destWorld->getSpawnLocation();
		$crossDimension = ChunkCache::getDimensionIdForWorld($world) !== ChunkCache::getDimensionIdForWorld($destWorld);

		$destWorld->requestChunkPopulation(
			$spawn->getFloorX() >> Chunk::COORD_BIT_SIZE,
			$spawn->getFloorZ() >> Chunk::COORD_BIT_SIZE,
			null
		)->onCompletion(
			function() use ($entity, $destWorld, $world, $pos, $entityId, $server, $crossDimension) : void{
				if($entity->isClosed()){
					return;
				}

				$targetPos = $destWorld->getSpawnLocation();
				try{
					$targetPos = $destWorld->getSafeSpawn($targetPos);
				}catch(\Throwable){
				}

				if($crossDimension){
					self::$crossDimensionPortalImmuneUntil[$entityId] = $server->getTick() + self::CROSS_DIMENSION_PORTAL_IMMUNITY_TICKS;
				}

				$world->addSound($pos, new EndermanTeleportSound());
				$entity->teleport($targetPos);
			},
			static function() : void{}
		);

		return true;
	}

	public static function tryActivateFromFrame(World $world, Vector3 $clickedFrame) : void{
		$cx = $clickedFrame->getFloorX();
		$cy = $clickedFrame->getFloorY();
		$cz = $clickedFrame->getFloorZ();

		foreach(self::FRAME_LAYOUT as [$lx, $lz]){
			if(self::tryLightPortalAtOrigin($world, $cx - $lx, $cy, $cz - $lz)){
				return;
			}
		}
	}

	private static function tryLightPortalAtOrigin(World $world, int $ox, int $oy, int $oz) : bool{
		foreach(self::FRAME_LAYOUT as [$lx, $lz, $facing]){
			$b = $world->getBlockAt($ox + $lx, $oy, $oz + $lz);
			if(!$b instanceof EndPortalFrame || !$b->hasEye() || $b->getFacing() !== $facing){
				return false;
			}
		}

		for($lx = 1; $lx <= 3; ++$lx){
			for($lz = 1; $lz <= 3; ++$lz){
				$b = $world->getBlockAt($ox + $lx, $oy, $oz + $lz);
				if(!($b instanceof EndPortal) && $b->getTypeId() !== BlockTypeIds::AIR && !$b->canBeReplaced()){
					return false;
				}
			}
		}

		$portal = VanillaBlocks::END_PORTAL();
		for($lx = 1; $lx <= 3; ++$lx){
			for($lz = 1; $lz <= 3; ++$lz){
				$world->setBlockAt($ox + $lx, $oy, $oz + $lz, clone $portal);
			}
		}

		$world->addSound(new Vector3($ox + 2.5, $oy + 0.5, $oz + 2.5), new EndPortalSpawnSound());
		return true;
	}
}
