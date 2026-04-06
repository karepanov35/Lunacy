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
use pocketmine\entity\Living;
use pocketmine\math\Axis;
use pocketmine\block\BlockTypeIds;
use pocketmine\world\generator\nether\NetherGenerator;
use pocketmine\world\generator\overworld\OverworldGenerator;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\sound\EndermanTeleportSound;
use pocketmine\item\Item;
use pocketmine\block\VanillaBlocks;
use pocketmine\network\mcpe\cache\ChunkCache;

class NetherPortal extends Transparent{

	protected int $axis = Axis::X;

	private static array $teleportCooldown = [];

	private static array $crossDimensionPortalImmuneUntil = [];

	private const TELEPORT_COOLDOWN_TICKS = 100;

	private const CROSS_DIMENSION_PORTAL_IMMUNITY_TICKS = 400;

	private const OVERWORLD_WORLD_NAME = "world";
	private const OVERWORLD_FALLBACK_NAME = "overworld";
	private const NETHER_WORLD_NAME = "nether";

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalAxis($this->axis);
	}

	public function getAxis() : int{
		return $this->axis;
	}

	/**
	 * @throws \InvalidArgumentException
	 * @return $this
	 */
	public function setAxis(int $axis) : self{
		if($axis !== Axis::X && $axis !== Axis::Z){
			throw new \InvalidArgumentException("Invalid axis");
		}
		$this->axis = $axis;
		return $this;
	}

	public function getLightLevel() : int{
		return 11;
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
		$nextAllowedTick = self::$teleportCooldown[$entityId] ?? -PHP_INT_MAX;
		if($currentTick < $nextAllowedTick){
			return true;
		}
		self::$teleportCooldown[$entityId] = $currentTick + self::TELEPORT_COOLDOWN_TICKS;

		$generator = strtolower($world->getProvider()->getWorldData()->getGenerator());
		$folderName = strtolower($world->getFolderName());

		$sourceIsNether = str_contains($folderName, "nether") || in_array($generator, ["nether", "hell"], true);
		$destWorldName = $sourceIsNether ? self::OVERWORLD_WORLD_NAME : self::NETHER_WORLD_NAME;

		$destGeneratorClass = $sourceIsNether ? OverworldGenerator::class : NetherGenerator::class;

		$worldManager = $server->getWorldManager();
		$destWorld = $worldManager->getWorldByName($destWorldName);
		if($destWorld === null){
			$options = WorldCreationOptions::create()
				->setGeneratorClass($destGeneratorClass)
				->setSeed($world->getSeed())
				->setDifficulty($world->getDifficulty());

			if($worldManager->isWorldGenerated($destWorldName)){
				$worldManager->loadWorld($destWorldName);
			}else{
				$worldManager->generateWorld($destWorldName, $options, true);
			}

			$destWorld = $worldManager->getWorldByName($destWorldName);
		}

		if($destWorld !== null && $sourceIsNether){
			$destGen = strtolower($destWorld->getProvider()->getWorldData()->getGenerator());
			if(in_array($destGen, ["nether", "hell"], true)){
				$destWorld = $worldManager->getWorldByName(self::OVERWORLD_FALLBACK_NAME);
				if($destWorld === null){
					$options = WorldCreationOptions::create()
						->setGeneratorClass(OverworldGenerator::class)
						->setSeed($world->getSeed())
						->setDifficulty($world->getDifficulty());
					if(!$worldManager->isWorldGenerated(self::OVERWORLD_FALLBACK_NAME)){
						$worldManager->generateWorld(self::OVERWORLD_FALLBACK_NAME, $options, true);
					}else{
						$worldManager->loadWorld(self::OVERWORLD_FALLBACK_NAME);
					}
					$destWorld = $worldManager->getWorldByName(self::OVERWORLD_FALLBACK_NAME);
				}
			}
		}

		if($destWorld === null){
			return true;
		}

		$pos = $entity->getPosition();
		$srcX = (int) floor($pos->x);
		$srcY = (int) floor($pos->y);
		$srcZ = (int) floor($pos->z);

		if($sourceIsNether){
			$destX = $srcX * 8;
			$destZ = $srcZ * 8;
		}else{
			$destX = self::floorDiv($srcX, 8);
			$destZ = self::floorDiv($srcZ, 8);
		}
		$destY = max($destWorld->getMinY(), min($destWorld->getMaxY() - 1, (float) $srcY));

		$chunkX = $destX >> Chunk::COORD_BIT_SIZE;
		$chunkZ = $destZ >> Chunk::COORD_BIT_SIZE;

		$crossDimension = ChunkCache::getDimensionIdForWorld($world) !== ChunkCache::getDimensionIdForWorld($destWorld);

		$destWorld->requestChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
			function() use ($entity, $destWorld, $destX, $destY, $destZ, $world, $pos, $entityId, $server, $crossDimension, $sourceIsNether) : void{
				if($entity->isClosed()){
					return;
				}

				$targetPos = $this->findDestinationPortalPosition($destWorld, $destX, $destY, $destZ);
				if($targetPos === null){
					$createdPos = $this->tryCreateMinimalPortal(
						$destWorld,
						$destX,
						(int) floor($destY),
						$destZ,
						$this->axis,
						!$sourceIsNether
					);
					if($createdPos !== null){
						$targetPos = $createdPos;
					}else{
						$targetPos = new Position(
							(float) $destX + 0.5,
							(float) $destY + 0.5,
							(float) $destZ + 0.5,
							$destWorld
						);
					}
				}

				try{
					$targetPos = $destWorld->getSafeSpawn($targetPos);
				}catch(\Throwable){
				}

				if($crossDimension){
					$axis = $this->axis;
					$ox = $axis === Axis::Z ? 1.0 : 0.0;
					$oz = $axis === Axis::X ? 1.0 : 0.0;
					$targetPos = new Position(
						$targetPos->x + $ox,
						$targetPos->y,
						$targetPos->z + $oz,
						$destWorld
					);
				}

				if($crossDimension){
					self::$crossDimensionPortalImmuneUntil[$entityId] = $server->getTick() + self::CROSS_DIMENSION_PORTAL_IMMUNITY_TICKS;
				}

				$world->addSound($pos, new EndermanTeleportSound());
				$entity->teleport($targetPos);
			},
			function() : void{
			}
		);

		return true;
	}

	private static function floorDiv(int $a, int $b) : int{
		$q = intdiv($a, $b);
		$r = $a % $b;
		if($r !== 0 && ($a < 0) !== ($b < 0)){
			--$q;
		}

		return $q;
	}

	/**
	 * @return Position|null
	 */
	private function findDestinationPortalPosition(World $destWorld, int $destX, float $destY, int $destZ) : ?Position{
		$centerY = (int) floor($destY);

		$minY = max($destWorld->getMinY(), $centerY - 16);
		$maxY = min($destWorld->getMaxY() - 1, $centerY + 16);

		$minX = $destX - 4;
		$maxX = $destX + 4;
		$minZ = $destZ - 4;
		$maxZ = $destZ + 4;

		$axisHint = $this->axis;
		for($pass = 0; $pass < 2; ++$pass){
			$requireAxisMatch = $pass === 0;
			for($y = $minY; $y <= $maxY; ++$y){
				for($x = $minX; $x <= $maxX; ++$x){
					for($z = $minZ; $z <= $maxZ; ++$z){
						$block = $destWorld->getBlockAt($x, $y, $z);
						if($block->getTypeId() !== BlockTypeIds::NETHER_PORTAL){
							continue;
						}
						if($requireAxisMatch && $block instanceof self && $block->getAxis() !== $axisHint){
							continue;
						}

						return new Position(
							(float) $x + 0.5,
							(float) $y + 0.5,
							(float) $z + 0.5,
							$destWorld
						);
					}
				}
			}
		}

		return null;
	}

	private function tryCreateMinimalPortal(World $world, int $destX, int $destYInt, int $destZ, int $axis, bool $requireNetherrackBase = false) : ?Position{
		$width = 4;
		$height = 5;

		$minBaseY = $world->getMinY() + 1;
		$maxBaseY = $world->getMaxY() - $height;
		if($minBaseY > $maxBaseY){
			return null;
		}

		$startBaseY = max($minBaseY, min($maxBaseY, $destYInt - 1));
		$searchRadius = 6;

		for($y = $startBaseY; $y >= $minBaseY; --$y){
			for($xOff = -$searchRadius; $xOff <= $searchRadius; ++$xOff){
				for($zOff = -$searchRadius; $zOff <= $searchRadius; ++$zOff){
					$centerX = $destX + $xOff;
					$centerZ = $destZ + $zOff;
					$cornerX = $axis === Axis::X ? $centerX - 1 : $centerX;
					$cornerZ = $axis === Axis::X ? $centerZ : $centerZ - 1;

					if(!$this->canPlaceMinimalPortalAt($world, $cornerX, $y, $cornerZ, $axis, $width, $height, $requireNetherrackBase)){
						continue;
					}

					$obsidian = VanillaBlocks::OBSIDIAN();
					for($yOff = 0; $yOff < $height; ++$yOff){
						for($wOff = 0; $wOff < $width; ++$wOff){
							$x = $axis === Axis::X ? ($cornerX + $wOff) : $cornerX;
							$z = $axis === Axis::Z ? ($cornerZ + $wOff) : $cornerZ;
							$yy = $y + $yOff;

							$isEdge = ($yOff === 0 || $yOff === $height - 1 || $wOff === 0 || $wOff === $width - 1);
							try{
								if($isEdge){
									$world->setBlockAt($x, $yy, $z, $obsidian, true);
								}else{
									$world->setBlockAt($x, $yy, $z, VanillaBlocks::NETHER_PORTAL()->setAxis($axis), true);
								}
							}catch(\Throwable){
								return null;
							}
						}
					}

					return new Position(
						(float) $centerX + 0.5,
						(float) $y + 1.5,
						(float) $centerZ + 0.5,
						$world
					);
				}
			}
		}

		return null;
	}

	private function canPlaceMinimalPortalAt(World $world, int $cornerX, int $frameBaseY, int $cornerZ, int $axis, int $width, int $height, bool $requireNetherrackBase) : bool{
		if($frameBaseY < $world->getMinY() + 1 || ($frameBaseY + $height - 1) >= $world->getMaxY()){
			return false;
		}

		for($yOff = 0; $yOff < $height; ++$yOff){
			for($wOff = 0; $wOff < $width; ++$wOff){
				$x = $axis === Axis::X ? ($cornerX + $wOff) : $cornerX;
				$z = $axis === Axis::Z ? ($cornerZ + $wOff) : $cornerZ;
				$y = $frameBaseY + $yOff;

				$isEdge = ($yOff === 0 || $yOff === $height - 1 || $wOff === 0 || $wOff === $width - 1);
				$block = $world->getBlockAt($x, $y, $z);
				if($isEdge){
					if($block->getTypeId() !== BlockTypeIds::AIR && $block->getTypeId() !== BlockTypeIds::OBSIDIAN){
						return false;
					}
				}else{
					if($block->getTypeId() !== BlockTypeIds::AIR && $block->getTypeId() !== BlockTypeIds::NETHER_PORTAL){
						return false;
					}
				}
			}
		}

		if($requireNetherrackBase){
			for($wOff = 0; $wOff < $width; ++$wOff){
				$x = $axis === Axis::X ? ($cornerX + $wOff) : $cornerX;
				$z = $axis === Axis::Z ? ($cornerZ + $wOff) : $cornerZ;
				$support = $world->getBlockAt($x, $frameBaseY - 1, $z);
				if($support->getTypeId() !== BlockTypeIds::NETHERRACK){
					return false;
				}
			}
		}

		return true;
	}
}
