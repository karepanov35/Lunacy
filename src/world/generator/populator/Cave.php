<?php

declare(strict_types=1);

namespace pocketmine\world\generator\populator;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use function floor;
use function pi;
use function sin;

class Cave implements Populator{

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random) : void{
		$overLap = 8;
		$firstSeed = $random->nextInt();
		$secondSeed = $random->nextInt();
		
		for($cxx = 0; $cxx < 1; $cxx++){
			for($czz = 0; $czz < 1; $czz++){
				$dcx = $chunkX + $cxx;
				$dcz = $chunkZ + $czz;
				for($cxxx = -$overLap; $cxxx <= $overLap; $cxxx++){
					for($czzz = -$overLap; $czzz <= $overLap; $czzz++){
						$dcxx = $dcx + $cxxx;
						$dczz = $dcz + $czzz;
						$this->pop($world, $dcxx, $dczz, $dcx, $dcz, new Random(($dcxx * $firstSeed) ^ ($dczz * $secondSeed) ^ $random->getSeed()));
					}
				}
			}
		}
	}

	private function pop(ChunkManager $world, int $x, int $z, int $chunkX, int $chunkZ, Random $random) : void{
		$c = $world->getChunk($x, $z);
		$oC = $world->getChunk($chunkX, $chunkZ);
		if($c === null || $oC === null){
			return;
		}

		$chunk = new Vector3($x << 4, 0, $z << 4);
		$originChunk = new Vector3($chunkX << 4, 0, $chunkZ << 4);
		
		if($random->nextBoundedInt(24) !== 0){
			return;
		}

		$numberOfCaves = $random->nextBoundedInt($random->nextBoundedInt($random->nextBoundedInt(18) + 1) + 1);
		for($caveCount = 0; $caveCount < $numberOfCaves; $caveCount++){
			$target = new Vector3(
				$chunk->getX() + $random->nextBoundedInt(16),
				$random->nextBoundedInt($random->nextBoundedInt(120) + 8),
				$chunk->getZ() + $random->nextBoundedInt(16)
			);

			$numberOfSmallCaves = 1;

			if($random->nextBoundedInt(4) === 0){
				$this->generateLargeCaveBranch($world, $originChunk, $target, new Random($random->nextInt()));
				$numberOfSmallCaves += $random->nextBoundedInt(4);
			}

			for($count = 0; $count < $numberOfSmallCaves; $count++){
				$randomHorizontalAngle = $random->nextFloat() * pi() * 2;
				$randomVerticalAngle = (($random->nextFloat() - 0.5) * 2) / 8;
				$horizontalScale = $random->nextFloat() * 2 + $random->nextFloat();

				if($random->nextBoundedInt(10) === 0){
					$horizontalScale *= $random->nextFloat() * $random->nextFloat() * 3 + 1;
				}

				$this->generateCaveBranch($world, $originChunk, $target, $horizontalScale, 1, $randomHorizontalAngle, $randomVerticalAngle, 0, 0, new Random($random->nextInt()));
			}
		}
	}

	private function generateCaveBranch(ChunkManager $world, Vector3 $chunk, Vector3 $target, float $horizontalScale, float $verticalScale, float $horizontalAngle, float $verticalAngle, int $startingNode, int $nodeAmount, Random $random) : void{
		$root = new Vector3($target->getX(), $target->getY(), $target->getZ());
		$horizontalOffset = 0.0;
		$verticalOffset = 0.0;

		if($nodeAmount <= 0){
			$size = 8 * 16;
			$nodeAmount = $size - $random->nextBoundedInt((int)($size / 4));
		}

		$intersectionMode = (int)($nodeAmount / 2) + (int)($nodeAmount / 4);
		$extraVerticalScale = $random->nextBoundedInt(6) === 0;

		if($startingNode === -1){
			$startingNode = (int)($nodeAmount / 2);
			$lastNode = true;
		}else{
			$lastNode = false;
		}

		$maxDistSq = 256 * 256;
		for(; $startingNode < $nodeAmount; $startingNode++){
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
					$this->generateCaveBranch($world, $chunk, $target, $random->nextFloat() * 0.5 + 0.5, 1, $horizontalAngle - pi() / 2, $verticalAngle / 3, $startingNode, $nodeAmount, new Random($random->nextInt()));
					$this->generateCaveBranch($world, $chunk, $target, $random->nextFloat() * 0.5 + 0.5, 1, $horizontalAngle + pi() / 2, $verticalAngle / 3, $startingNode, $nodeAmount, new Random($random->nextInt()));
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

			$node = new CaveNode($world, $startWorld, $endWorld, $target, $verticalSize, $horizontalSize);

			if($node->canPlace()){
				$node->place();
			}

			if($lastNode){
				break;
			}
		}
	}

	private function generateLargeCaveBranch(ChunkManager $world, Vector3 $chunk, Vector3 $target, Random $random) : void{
		$this->generateCaveBranch($world, $chunk, $target, $random->nextFloat() * 6 + 1, 0.5, 0, 0, -1, -1, $random);
	}
}

class CaveNode{
	private ChunkManager $world;
	private Vector3 $start;
	private Vector3 $end;
	private Vector3 $target;
	private float $verticalSize;
	private float $horizontalSize;

	public function __construct(ChunkManager $world, Vector3 $startWorld, Vector3 $endWorld, Vector3 $target, float $verticalSize, float $horizontalSize){
		$this->world = $world;
		$this->start = $startWorld;
		$this->end = $endWorld;
		$this->target = $target;
		$this->verticalSize = $verticalSize;
		$this->horizontalSize = $horizontalSize;
	}

	public function canPlace() : bool{
		$water = VanillaBlocks::WATER()->getStateId();
		$minY = $this->world->getMinY();
		$maxY = $this->world->getMaxY();

		for($x = $this->start->getFloorX(); $x < $this->end->getFloorX(); $x++){
			for($z = $this->start->getFloorZ(); $z < $this->end->getFloorZ(); $z++){
				for($y = $this->end->getFloorY() + 1; $y >= $this->start->getFloorY() - 1; $y--){
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
		$minY = $this->world->getMinY();
		$maxY = $this->world->getMaxY();

		for($x = $this->start->getFloorX(); $x < $this->end->getFloorX(); $x++){
			$xOffset = ($x + 0.5 - $this->target->getX()) / $this->horizontalSize;
			for($z = $this->start->getFloorZ(); $z < $this->end->getFloorZ(); $z++){
				$zOffset = ($z + 0.5 - $this->target->getZ()) / $this->horizontalSize;
				if(($xOffset * $xOffset + $zOffset * $zOffset) >= 1){
					continue;
				}
				for($y = $this->end->getFloorY() - 1; $y >= $this->start->getFloorY(); $y--){
					if($y < $minY || $y >= $maxY){
						continue;
					}
					$yOffset = ($y + 0.5 - $this->target->getY()) / $this->verticalSize;
					if($yOffset > -0.7 && ($xOffset * $xOffset + $yOffset * $yOffset + $zOffset * $zOffset) < 1){
						$blockId = $this->world->getBlockAt($x, $y, $z)->getStateId();

						if($blockId === $stone || $blockId === $dirt || $blockId === $grass){
							if($y < 10){
								$this->world->setBlockAt($x, $y, $z, VanillaBlocks::LAVA());
							}else{
								if($blockId === $grass && $this->world->getBlockAt($x, $y - 1, $z)->getStateId() === $dirt){
									$this->world->setBlockAt($x, $y - 1, $z, VanillaBlocks::GRASS());
								}
								$this->world->setBlockAt($x, $y, $z, VanillaBlocks::AIR());
							}
						}
					}
				}
			}
		}
	}
}
