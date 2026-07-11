<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function floor;
use function pi;
use function sin;

class Cave implements Populator{

	private const ORIGIN_SEED_X = 341873128712;
	private const ORIGIN_SEED_Z = 132897987541;

	public function __construct(
		private int $worldSeed
	){}

	/**
	 * Same cave origin must produce the same tunnels no matter which neighboring chunk is populating.
	 */
	private function originRandom(int $originChunkX, int $originChunkZ) : Random{
		return new Random(($originChunkX * self::ORIGIN_SEED_X) ^ ($originChunkZ * self::ORIGIN_SEED_Z) ^ $this->worldSeed);
	}

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		// Origins from a wide neighborhood (vanilla-style). Only the center chunk is carved, with
		// world-deterministic RNG so adjacent chunks produce matching tunnel halves at borders.
		$overlap = 8;

		$minX = $chunkX << Chunk::COORD_BIT_SIZE;
		$maxX = $minX + Chunk::COORD_MASK;
		$minZ = $chunkZ << Chunk::COORD_BIT_SIZE;
		$maxZ = $minZ + Chunk::COORD_MASK;

		for($ox = -$overlap; $ox <= $overlap; ++$ox){
			for($oz = -$overlap; $oz <= $overlap; ++$oz){
				$targetChunkX = $chunkX + $ox;
				$targetChunkZ = $chunkZ + $oz;
				$this->pop(
					$world,
					$targetChunkX,
					$targetChunkZ,
					$minX,
					$maxX,
					$minZ,
					$maxZ,
					$this->originRandom($targetChunkX, $targetChunkZ)
				);
			}
		}
	}

	private function pop(
		ChunkManager $world,
		int $originChunkX,
		int $originChunkZ,
		int $carveMinX,
		int $carveMaxX,
		int $carveMinZ,
		int $carveMaxZ,
		Random $random
	) : void{
		if($world->getChunk($originChunkX, $originChunkZ) === null){
			return;
		}

		$chunkBase = new Vector3($originChunkX << Chunk::COORD_BIT_SIZE, 0, $originChunkZ << Chunk::COORD_BIT_SIZE);

		if($random->nextBoundedInt(24) !== 0){
			return;
		}

		$numberOfCaves = $random->nextBoundedInt($random->nextBoundedInt($random->nextBoundedInt(18) + 1) + 1);
		for($caveCount = 0; $caveCount < $numberOfCaves; ++$caveCount){
			$target = new Vector3(
				$chunkBase->getX() + $random->nextBoundedInt(16),
				$random->nextBoundedInt($random->nextBoundedInt(120) + 8),
				$chunkBase->getZ() + $random->nextBoundedInt(16)
			);

			$numberOfSmallCaves = 1;

			if($random->nextBoundedInt(4) === 0){
				$this->generateLargeCaveBranch($world, $target, $carveMinX, $carveMaxX, $carveMinZ, $carveMaxZ, new Random($random->nextInt()));
				$numberOfSmallCaves += $random->nextBoundedInt(4);
			}

			for($count = 0; $count < $numberOfSmallCaves; ++$count){
				$randomHorizontalAngle = $random->nextFloat() * pi() * 2;
				$randomVerticalAngle = (($random->nextFloat() - 0.5) * 2) / 8;
				$horizontalScale = $random->nextFloat() * 2 + $random->nextFloat();

				if($random->nextBoundedInt(10) === 0){
					$horizontalScale *= $random->nextFloat() * $random->nextFloat() * 3 + 1;
				}

				$this->generateCaveBranch(
					$world,
					$target,
					$horizontalScale,
					1.0,
					$randomHorizontalAngle,
					$randomVerticalAngle,
					0,
					0,
					$carveMinX,
					$carveMaxX,
					$carveMinZ,
					$carveMaxZ,
					new Random($random->nextInt())
				);
			}
		}
	}

	private function generateCaveBranch(
		ChunkManager $world,
		Vector3 $target,
		float $horizontalScale,
		float $verticalScale,
		float $horizontalAngle,
		float $verticalAngle,
		int $startingNode,
		int $nodeAmount,
		int $carveMinX,
		int $carveMaxX,
		int $carveMinZ,
		int $carveMaxZ,
		Random $random
	) : void{
		$root = new Vector3($target->getX(), $target->getY(), $target->getZ());
		$horizontalOffset = 0.0;
		$verticalOffset = 0.0;

		if($nodeAmount <= 0){
			$size = 8 * 16;
			$nodeAmount = $size - $random->nextBoundedInt((int) ($size / 4));
		}

		$intersectionMode = (int) ($nodeAmount / 2) + (int) ($nodeAmount / 4);
		$extraVerticalScale = $random->nextBoundedInt(6) === 0;

		if($startingNode === -1){
			$startingNode = (int) ($nodeAmount / 2);
			$lastNode = true;
		}else{
			$lastNode = false;
		}

		$maxDistSq = 256 * 256;
		for(; $startingNode < $nodeAmount; ++$startingNode){
			$horizontalSize = 1.5 + sin($startingNode * pi() / $nodeAmount) * $horizontalScale;
			$verticalSize = $horizontalSize * $verticalScale;

			$dx = sin($horizontalAngle) * cos($verticalAngle);
			$dy = sin($verticalAngle);
			$dz = cos($horizontalAngle) * cos($verticalAngle);
			$target = $target->add($dx, $dy, $dz);

			if($extraVerticalScale){
				$verticalAngle *= 0.92;
			}else{
				$verticalAngle *= 0.7;
			}

			$verticalAngle += $verticalOffset * 0.1;
			$horizontalAngle += $horizontalOffset * 0.1;
			$verticalOffset *= 0.9;
			$horizontalOffset *= 0.75;
			$verticalOffset += ($random->nextFloat() - $random->nextFloat()) * $random->nextFloat() * 2;
			$horizontalOffset += ($random->nextFloat() - $random->nextFloat()) * $random->nextFloat() * 4;

			if(!$lastNode){
				if($startingNode === $intersectionMode && $horizontalScale > 1 && $nodeAmount > 0){
					$this->generateCaveBranch($world, $target, $random->nextFloat() * 0.5 + 0.5, 1.0, $horizontalAngle - pi() / 2, $verticalAngle / 3, $startingNode, $nodeAmount, $carveMinX, $carveMaxX, $carveMinZ, $carveMaxZ, new Random($random->nextInt()));
					$this->generateCaveBranch($world, $target, $random->nextFloat() * 0.5 + 0.5, 1.0, $horizontalAngle + pi() / 2, $verticalAngle / 3, $startingNode, $nodeAmount, $carveMinX, $carveMaxX, $carveMinZ, $carveMaxZ, new Random($random->nextInt()));
					return;
				}

				if($random->nextBoundedInt(4) === 0){
					continue;
				}
			}

			$xOffset = $target->getX() - $root->getX();
			$zOffset = $target->getZ() - $root->getZ();
			if($xOffset * $xOffset + $zOffset * $zOffset > $maxDistSq){
				return;
			}

			$startWorld = new Vector3(
				(int) floor($target->getX() - $horizontalSize) - 1,
				(int) floor($target->getY() - $verticalSize) - 1,
				(int) floor($target->getZ() - $horizontalSize) - 1
			);
			$endWorld = new Vector3(
				(int) floor($target->getX() + $horizontalSize) + 1,
				(int) floor($target->getY() + $verticalSize) + 1,
				(int) floor($target->getZ() + $horizontalSize) + 1
			);

			$node = new CaveNode($world, $startWorld, $endWorld, $target, $verticalSize, $horizontalSize, $carveMinX, $carveMaxX, $carveMinZ, $carveMaxZ);

			if($node->canPlace()){
				$node->place();
			}

			if($lastNode){
				break;
			}
		}
	}

	private function generateLargeCaveBranch(
		ChunkManager $world,
		Vector3 $target,
		int $carveMinX,
		int $carveMaxX,
		int $carveMinZ,
		int $carveMaxZ,
		Random $random
	) : void{
		$this->generateCaveBranch($world, $target, $random->nextFloat() * 6 + 1, 0.5, 0.0, 0.0, -1, -1, $carveMinX, $carveMaxX, $carveMinZ, $carveMaxZ, $random);
	}
}

class CaveNode{
	public function __construct(
		private ChunkManager $world,
		private Vector3 $start,
		private Vector3 $end,
		private Vector3 $target,
		private float $verticalSize,
		private float $horizontalSize,
		private int $carveMinX,
		private int $carveMaxX,
		private int $carveMinZ,
		private int $carveMaxZ
	){}

	public function canPlace() : bool{
		$water = VanillaBlocks::WATER()->getStateId();
		$minY = $this->world->getMinY();
		$maxY = $this->world->getMaxY();

		for($x = $this->start->getFloorX(); $x < $this->end->getFloorX(); ++$x){
			for($z = $this->start->getFloorZ(); $z < $this->end->getFloorZ(); ++$z){
				for($y = $this->end->getFloorY() + 1; $y >= $this->start->getFloorY() - 1; --$y){
					if($y < $minY || $y >= $maxY){
						continue;
					}
					if($this->world->getBlockAt($x, $y, $z)->getStateId() === $water){
						return false;
					}
				}
			}
		}
		return true;
	}

	public function place() : void{
		$stone = VanillaBlocks::STONE()->getStateId();
		$dirt = VanillaBlocks::DIRT()->getStateId();
		$grass = VanillaBlocks::GRASS()->getStateId();
		$gravel = VanillaBlocks::GRAVEL()->getStateId();
		$minY = $this->world->getMinY();
		$maxY = $this->world->getMaxY();

		for($x = $this->start->getFloorX(); $x < $this->end->getFloorX(); ++$x){
			if($x < $this->carveMinX || $x > $this->carveMaxX){
				continue;
			}
			$xOffset = ($x + 0.5 - $this->target->getX()) / $this->horizontalSize;
			for($z = $this->start->getFloorZ(); $z < $this->end->getFloorZ(); ++$z){
				if($z < $this->carveMinZ || $z > $this->carveMaxZ){
					continue;
				}
				$zOffset = ($z + 0.5 - $this->target->getZ()) / $this->horizontalSize;
				if(($xOffset * $xOffset + $zOffset * $zOffset) >= 1){
					continue;
				}
				for($y = $this->end->getFloorY() - 1; $y >= $this->start->getFloorY(); --$y){
					if($y < $minY || $y >= $maxY){
						continue;
					}
					$yOffset = ($y + 0.5 - $this->target->getY()) / $this->verticalSize;
					if($yOffset > -0.7 && ($xOffset * $xOffset + $yOffset * $yOffset + $zOffset * $zOffset) < 1){
						$blockId = $this->world->getBlockAt($x, $y, $z)->getStateId();

						if($blockId === $stone || $blockId === $dirt || $blockId === $grass || $blockId === $gravel){
							if($y < 10){
								$this->world->setBlockAt($x, $y, $z, VanillaBlocks::LAVA());
							}else{
								$this->world->setBlockAt($x, $y, $z, VanillaBlocks::AIR());
							}
						}
					}
				}
			}
		}
	}
}
