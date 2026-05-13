<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\object\tree\CherryTree as DecoratorCherryTree;
use function cos;
use function deg2rad;
use function round;
use function sin;
use function sqrt;

/**
 * Cherry tree grown from a sapling — same shape as {@see DecoratorCherryTree}.
 */
class CherryTree extends Tree{

	public function __construct(){
		parent::__construct(VanillaBlocks::CHERRY_LOG(), VanillaBlocks::CHERRY_LEAVES(), 6);
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		$this->treeHeight = $random->nextBoundedInt(3) + 5;
		return parent::getBlockTransaction($world, $x, $y, $z, $random);
	}

	public function canPlaceObject(ChunkManager $world, int $x, int $y, int $z, Random $random) : bool{
		$h        = $world->getMaxY();
		$trunkTop = $y + $this->treeHeight - 1;
		for($yy = 0; $yy <= $this->treeHeight + 5; ++$yy){
			$wy = $y + $yy;
			if($wy < 0 || $wy >= $h) return false;
			$r = ($wy <= $trunkTop) ? 1 : 5;
			for($xx = -$r; $xx <= $r; ++$xx){
				for($zz = -$r; $zz <= $r; ++$zz){
					if(!$this->canOverride($world->getBlockAt($x + $xx, $wy, $z + $zz))){
						return false;
					}
				}
			}
		}
		return true;
	}

	protected function placeTrunk(int $x, int $y, int $z, Random $random, int $trunkHeight, BlockTransaction $transaction) : void{
		$transaction->addBlockAt($x, $y - 1, $z, VanillaBlocks::DIRT());
		for($yy = 0; $yy < $trunkHeight; ++$yy){
			if($this->canOverride($transaction->fetchBlockAt($x, $y + $yy, $z))){
				$transaction->addBlockAt($x, $y + $yy, $z, $this->trunkBlock);
			}
		}
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		$trunkTop    = $y + $this->treeHeight - 1;
		$branchCount = $random->nextBoundedInt(2) + 2;

		for($i = 0; $i < $branchCount; ++$i){
			$angle       = ($i * 360 / $branchCount) + $random->nextBoundedInt(40) - 20;
			$rad         = deg2rad($angle);
			$branchLen   = $random->nextBoundedInt(2) + 3;
			$branchBaseY = $trunkTop - $random->nextBoundedInt(2);

			for($step = 1; $step <= $branchLen; ++$step){
				$dx = (int) round(cos($rad) * $step);
				$dz = (int) round(sin($rad) * $step);
				$dy = ($step >= $branchLen) ? 1 : 0;
				$bx = $x + $dx;
				$by = $branchBaseY + $dy;
				$bz = $z + $dz;

				if($this->canOverride($transaction->fetchBlockAt($bx, $by, $bz))){
					$transaction->addBlockAt($bx, $by, $bz, $this->trunkBlock);
				}
				if($step === $branchLen){
					$rx = $random->nextBoundedInt(2) + 3;
					$this->placeCanopyCluster($bx, $by, $bz, $rx, $random, $transaction);
				}
			}
		}

		$this->placeCanopyCluster($x, $trunkTop + 1, $z, 2, $random, $transaction);
	}

	private function placeCanopyCluster(int $cx, int $cy, int $cz, int $rx, Random $random, BlockTransaction $transaction) : void{
		$ry = 2;
		for($dx = -$rx; $dx <= $rx; ++$dx){
			for($dz = -$rx; $dz <= $rx; ++$dz){
				for($dy = -$ry; $dy <= $ry; ++$dy){
					$nx   = $dx / $rx; $ny = $dy / $ry; $nz = $dz / $rx;
					$dist = $nx * $nx + $ny * $ny + $nz * $nz;
					if($dist > 1.0) continue;
					if($dist > 0.72 && $random->nextBoundedInt(3) === 0) continue;
					if(!$transaction->fetchBlockAt($cx + $dx, $cy + $dy, $cz + $dz)->isSolid()){
						$transaction->addBlockAt($cx + $dx, $cy + $dy, $cz + $dz, $this->leafBlock);
					}
				}
				$hd = sqrt($dx * $dx + $dz * $dz);
				if($hd >= $rx - 1.2 && $hd <= $rx + 0.6){
					$maxHang = $random->nextBoundedInt(3) + 1;
					for($h = 1; $h <= $maxHang; ++$h){
						if($h > 1 && $random->nextBoundedInt(2) === 0) break;
						if(!$transaction->fetchBlockAt($cx + $dx, $cy - $ry - $h, $cz + $dz)->isSolid()){
							$transaction->addBlockAt($cx + $dx, $cy - $ry - $h, $cz + $dz, $this->leafBlock);
						}
					}
				}
			}
		}
	}
}
