<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

class BigTree extends Tree{
	public array $overridable = [];

	private Random $random;
	private float $trunkHeightMultiplier = 0.618;
	private int $trunkHeight;
	private int $leafAmount = 1;
	private int $leafDistanceLimit = 5;
	private float $widthScale = 1.0;
	private float $branchSlope = 0.381;
	private int $totalHeight;
	private int $baseHeight = 5;

	public function __construct(){
		parent::__construct(VanillaBlocks::OAK_LOG(), VanillaBlocks::OAK_LEAVES());
		
		$air = VanillaBlocks::AIR()->getStateId();
		$leaves = VanillaBlocks::OAK_LEAVES()->getStateId();
		$sapling = VanillaBlocks::OAK_SAPLING()->getStateId();
		
		$this->overridable = [
			$air => true,
			$leaves => true,
			$sapling => true
		];
	}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		$blockId = $world->getBlockAt($x, $y, $z)->getStateId();
		$water = VanillaBlocks::WATER()->getStateId();
		$stillWater = VanillaBlocks::WATER()->getStateId();
		
		if($blockId === $water || $blockId === $stillWater){
			return false;
		}
		
		$base = new Vector3($x, $y, $z);
		$this->totalHeight = $this->baseHeight + $random->nextBoundedInt(12);
		$availableSpace = $this->getAvailableBlockSpace($world, $base, $base->add(0, $this->totalHeight - 1, 0));
		
		if($availableSpace > $this->baseHeight || $availableSpace === -1){
			if($availableSpace !== -1){
				$this->totalHeight = $availableSpace;
			}
			return true;
		}
		return false;
	}

	public function placeObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : void{
		$this->random = $random;
		$this->trunkHeight = (int)($this->totalHeight * $this->trunkHeightMultiplier);
		$leaves = $this->getLeafGroupPoints($world, $x, $y, $z);
		
		foreach($leaves as $leaf){
			$leafGroup = $leaf[0];
			$groupX = $leafGroup->getFloorX();
			$groupY = $leafGroup->getFloorY();
			$groupZ = $leafGroup->getFloorZ();
			
			for($yy = $groupY; $yy < $groupY + $this->leafDistanceLimit; ++$yy){
				$this->generateGroupLayer($world, $groupX, $yy, $groupZ, $this->getLeafGroupLayerSize($yy - $groupY));
			}
		}
		
		// Place trunk
		for($yy = $y - 1; $yy <= $y + $this->trunkHeight; ++$yy){
			$world->setBlockAt($x, $yy, $z, $this->trunkBlock);
		}
		
		$this->generateBranches($world, $x, $y, $z, $leaves);
	}

	private function getLeafGroupPoints(ChunkManager $world, int $x, int $y, int $z) : array{
		$amount = $this->leafAmount * $this->totalHeight / 13;
		$groupsPerLayer = (int)(1.382 + $amount * $amount);

		if($groupsPerLayer === 0){
			$groupsPerLayer = 1;
		}

		$trunkTopY = $y + $this->trunkHeight;
		$groups = [];
		$groupY = $y + $this->totalHeight - $this->leafDistanceLimit;
		$groups[] = [new Vector3($x, $groupY, $z), $trunkTopY];

		for($currentLayer = (int)($this->totalHeight - $this->leafDistanceLimit); $currentLayer >= 0; $currentLayer--){
			$layerSize = $this->getRoughLayerSize($currentLayer);

			if($layerSize < 0){
				$groupY--;
				continue;
			}

			for($count = 0; $count < $groupsPerLayer; $count++){
				$scale = $this->widthScale * $layerSize * ($this->random->nextFloat() + 0.328);
				$randomOffset = Vector2::createRandomDirection($this->random)->multiply($scale);
				$groupX = (int)($randomOffset->getX() + $x + 0.5);
				$groupZ = (int)($randomOffset->getY() + $z + 0.5);
				$group = new Vector3($groupX, $groupY, $groupZ);
				
				if($this->getAvailableBlockSpace($world, $group, $group->add(0, $this->leafDistanceLimit, 0)) !== -1){
					continue;
				}
				
				$xOff = (int)($x - $groupX);
				$zOff = (int)($z - $groupZ);
				$horizontalDistanceToTrunk = sqrt($xOff * $xOff + $zOff * $zOff);
				$verticalDistanceToTrunk = $horizontalDistanceToTrunk * $this->branchSlope;
				$yDiff = (int)($groupY - $verticalDistanceToTrunk);
				
				$base = $yDiff > $trunkTopY ? $trunkTopY : $yDiff;
				
				if($this->getAvailableBlockSpace($world, new Vector3($x, $base, $z), $group) === -1){
					$groups[] = [$group, $base];
				}
			}
			$groupY--;
		}
		return $groups;
	}

	private function getLeafGroupLayerSize(int $y) : int{
		if($y >= 0 && $y < $this->leafDistanceLimit){
			return (int)(($y !== ($this->leafDistanceLimit - 1)) ? 3 : 2);
		}
		return -1;
	}

	private function generateGroupLayer(ChunkManager $world, int $x, int $y, int $z, int $size) : void{
		for($xx = $x - $size; $xx <= $x + $size; $xx++){
			for($zz = $z - $size; $zz <= $z + $size; $zz++){
				$sizeX = abs($x - $xx) + 0.5;
				$sizeZ = abs($z - $zz) + 0.5;
				
				if(($sizeX * $sizeX + $sizeZ * $sizeZ) <= ($size * $size)){
					$blockId = $world->getBlockAt($xx, $y, $zz)->getStateId();
					if(isset($this->overridable[$blockId])){
						$world->setBlockAt($xx, $y, $zz, $this->leafBlock);
					}
				}
			}
		}
	}

	private function getRoughLayerSize(int $layer) : float{
		$halfHeight = $this->totalHeight / 2;
		
		if($layer < ($this->totalHeight / 3)){
			return -1;
		}elseif($layer === $halfHeight){
			return $halfHeight / 4;
		}elseif($layer >= $this->totalHeight || $layer <= 0){
			return 0;
		}else{
			return sqrt($halfHeight * $halfHeight - ($layer - $halfHeight) * ($layer - $halfHeight)) / 2;
		}
	}

	private function generateBranches(ChunkManager $world, int $x, int $y, int $z, array $groups) : void{
		foreach($groups as $group){
			$baseY = $group[1];
			
			if(($baseY - $y) >= ($this->totalHeight * 0.2)){
				$base = new Vector3($x, $baseY, $z);
				$target = $group[0];
				
				// Draw line from base to target
				$distance = $base->distance($target);
				$steps = (int)ceil($distance);
				
				for($i = 0; $i <= $steps; $i++){
					$progress = $i / $steps;
					$posX = (int)($base->x + ($target->x - $base->x) * $progress);
					$posY = (int)($base->y + ($target->y - $base->y) * $progress);
					$posZ = (int)($base->z + ($target->z - $base->z) * $progress);
					
					$world->setBlockAt($posX, $posY, $posZ, $this->trunkBlock);
				}
			}
		}
	}

	private function getAvailableBlockSpace(ChunkManager $world, Vector3 $from, Vector3 $to) : int{
		$count = 0;
		$distance = $from->distance($to);
		$steps = (int)ceil($distance);
		
		for($i = 0; $i <= $steps; $i++){
			$progress = $i / $steps;
			$x = (int)($from->x + ($to->x - $from->x) * $progress);
			$y = (int)($from->y + ($to->y - $from->y) * $progress);
			$z = (int)($from->z + ($to->z - $from->z) * $progress);
			
			$blockId = $world->getBlockAt($x, $y, $z)->getStateId();
			if(!isset($this->overridable[$blockId])){
				return $count;
			}
			$count++;
		}
		return -1;
	}
}
