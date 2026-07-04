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

use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\entity\mob\EnderDragon;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\cache\ChunkCache;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\end\TheEndGenerator;
use pocketmine\world\generator\overworld\OverworldGenerator;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\sound\EndPortalFrameFillSound;
use pocketmine\world\sound\EndPortalSpawnSound;
use pocketmine\world\sound\PortalTravelSound;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;

class EndPortal extends Transparent{

	/** @var array<int, int> */
	private static array $teleportCooldown = [];

	/** @var array<int, int> */
	private static array $crossDimensionPortalImmuneUntil = [];
	private static ?EndPortalFrameFillSound $frameFillSound = null;
	private static ?EndPortalSpawnSound $spawnSound = null;
	private static ?PortalTravelSound $travelSound = null;

	private const TELEPORT_COOLDOWN_TICKS = 100;
	private const CROSS_DIMENSION_PORTAL_IMMUNITY_TICKS = 400;
	private const OVERWORLD_WORLD_NAME = "world";
	private const THE_END_WORLD_NAME = "the_end";

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
		if($entity instanceof EnderDragon){
			return true;
		}

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
		$nextAllowedTick = self::$teleportCooldown[$entityId] ?? -PHP_INT_MAX;
		if($currentTick < $nextAllowedTick){
			return true;
		}
		self::$teleportCooldown[$entityId] = $currentTick + self::TELEPORT_COOLDOWN_TICKS;

		$sourceDim = ChunkCache::getDimensionIdForWorld($world);
		if($sourceDim === DimensionIds::THE_END && EnderDragon::isAliveInWorld($world)){
			return true;
		}

		$destWorldName = $sourceDim === DimensionIds::THE_END ? self::OVERWORLD_WORLD_NAME : self::THE_END_WORLD_NAME;

		$worldManager = $server->getWorldManager();
		$destWorld = $worldManager->getWorldByName($destWorldName);
		if($destWorld === null){
			$seed = $world->getSeed();
			$difficulty = $world->getDifficulty();
			if($destWorldName === self::THE_END_WORLD_NAME){
				$options = WorldCreationOptions::create()
					->setGeneratorClass(TheEndGenerator::class)
					->setSeed($seed)
					->setDifficulty($difficulty)
					->setSpawnPosition(new Vector3(100, 49, 0));
				if($worldManager->isWorldGenerated(self::THE_END_WORLD_NAME)){
					$worldManager->loadWorld(self::THE_END_WORLD_NAME);
				}else{
					$worldManager->generateWorld(self::THE_END_WORLD_NAME, $options, true);
				}
			}else{
				$options = WorldCreationOptions::create()
					->setGeneratorClass(OverworldGenerator::class)
					->setSeed($seed)
					->setDifficulty($difficulty);
				if($worldManager->isWorldGenerated(self::OVERWORLD_WORLD_NAME)){
					$worldManager->loadWorld(self::OVERWORLD_WORLD_NAME);
				}else{
					$worldManager->generateWorld(self::OVERWORLD_WORLD_NAME, $options, true);
				}
			}
			$destWorld = $worldManager->getWorldByName($destWorldName);
		}

		if($destWorld === null){
			return true;
		}

		$pos = $entity->getPosition();
		$spawn = $destWorld->getSpawnLocation();
		$chunkX = $spawn->getFloorX() >> Chunk::COORD_BIT_SIZE;
		$chunkZ = $spawn->getFloorZ() >> Chunk::COORD_BIT_SIZE;

		$crossDimension = ChunkCache::getDimensionIdForWorld($world) !== ChunkCache::getDimensionIdForWorld($destWorld);

		$destWorld->requestChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
			function() use ($entity, $destWorld, $world, $pos, $entityId, $server, $crossDimension) : void{
				if($entity->isClosed()){
					return;
				}

				if(ChunkCache::getDimensionIdForWorld($destWorld) === DimensionIds::THE_END){
					$destWorld->loadChunk(0, 0);
					TheEndGenerator::ensureExitPortal($destWorld);
					TheEndGenerator::ensureObsidianPillars($destWorld);
					TheEndGenerator::ensureEndCrystals($destWorld);
					TheEndGenerator::ensureEnderDragon($destWorld);
				}

				$targetPos = $destWorld->getSpawnLocation();
				try{
					$targetPos = $destWorld->getSafeSpawn($targetPos);
				}catch(\Throwable){
				}

				if($crossDimension){
					self::$crossDimensionPortalImmuneUntil[$entityId] = $server->getTick() + self::CROSS_DIMENSION_PORTAL_IMMUNITY_TICKS;
				}

				$world->addSound($pos, self::$travelSound ??= new PortalTravelSound());
				$destWorld->addSound($targetPos, self::$travelSound ??= new PortalTravelSound());
				$entity->teleport($targetPos);
			},
			static function() : void{
			}
		);

		return true;
	}

	public static function playFrameFillSound(World $world, Vector3 $framePos) : void{
		$world->addSound($framePos->add(0.5, 0.5, 0.5), self::$frameFillSound ??= new EndPortalFrameFillSound());
	}

	public static function tryActivateFromFrame(World $world, Vector3 $clickedFrame) : void{
		$cx = $clickedFrame->getFloorX();
		$cy = $clickedFrame->getFloorY();
		$cz = $clickedFrame->getFloorZ();

		$locals = [
			[0, 1], [0, 2], [0, 3],
			[4, 1], [4, 2], [4, 3],
			[1, 0], [2, 0], [3, 0],
			[1, 4], [2, 4], [3, 4],
		];
		foreach($locals as [$lx, $lz]){
			if(self::tryLightPortalAtOrigin($world, $cx - $lx, $cy, $cz - $lz)){
				return;
			}
		}
	}

	private static function tryLightPortalAtOrigin(World $world, int $Ox, int $Oy, int $Oz) : bool{
		$framePositions = [
			[0, 1], [0, 2], [0, 3],
			[4, 1], [4, 2], [4, 3],
			[1, 0], [2, 0], [3, 0],
			[1, 4], [2, 4], [3, 4],
		];
		foreach($framePositions as [$lx, $lz]){
			$b = $world->getBlockAt($Ox + $lx, $Oy, $Oz + $lz);
			if(!$b instanceof EndPortalFrame || !$b->hasEye()){
				return false;
			}
			if(!self::isFrameFacingInward($b, $Ox, $Oz)){
				return false;
			}
		}

		$corners = [[0, 0], [0, 4], [4, 0], [4, 4]];
		foreach($corners as [$lx, $lz]){
			if(!self::isReplaceableForPortalInterior($world->getBlockAt($Ox + $lx, $Oy, $Oz + $lz))){
				return false;
			}
		}

		$portal = VanillaBlocks::END_PORTAL();
		$alreadyActive = true;
		for($lx = 1; $lx <= 3; ++$lx){
			for($lz = 1; $lz <= 3; ++$lz){
				$b = $world->getBlockAt($Ox + $lx, $Oy, $Oz + $lz);
				if($b instanceof EndPortal){
					continue;
				}
				$alreadyActive = false;
				if(!self::isReplaceableForPortalInterior($b)){
					return false;
				}
			}
		}

		for($lx = 1; $lx <= 3; ++$lx){
			for($lz = 1; $lz <= 3; ++$lz){
				$world->setBlockAt($Ox + $lx, $Oy, $Oz + $lz, clone $portal);
			}
		}

		if(!$alreadyActive){
			for($lx = 1; $lx <= 3; ++$lx){
				for($lz = 1; $lz <= 3; ++$lz){
					$pos = new Vector3($Ox + $lx, $Oy, $Oz + $lz);
					for($i = 0; $i < 4; ++$i){
						$world->addParticle($pos->add(0.5, 0.5, 0.5), new PortalParticle());
					}
				}
			}

			$world->addSound(new Vector3($Ox + 2.5, $Oy + 0.5, $Oz + 2.5), self::$spawnSound ??= new EndPortalSpawnSound());
		}

		return true;
	}

	private static function isFrameFacingInward(EndPortalFrame $frame, int $originX, int $originZ) : bool{
		$pos = $frame->getPosition();
		$localX = $pos->getFloorX() - $originX;
		$localZ = $pos->getFloorZ() - $originZ;

		$expectedFacing = match(true){
			$localX === 0 => Facing::EAST,
			$localX === 4 => Facing::WEST,
			$localZ === 0 => Facing::SOUTH,
			$localZ === 4 => Facing::NORTH,
			default => null
		};

		return $expectedFacing !== null && $frame->getFacing() === $expectedFacing;
	}

	private static function isReplaceableForPortalInterior(Block $b) : bool{
		if($b instanceof EndPortal){
			return true;
		}
		return $b->getTypeId() === BlockTypeIds::AIR || $b->canBeReplaced();
	}
}
