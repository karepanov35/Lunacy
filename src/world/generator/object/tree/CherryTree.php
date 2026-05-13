<?php

declare(strict_types=1);

namespace pocketmine\world\generator\object\tree;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use function abs;
use function cos;
use function deg2rad;
use function max;
use function min;
use function round;
use function sin;
use function sqrt;

/**
 * Vanilla-accurate cherry tree for world generation.
 *
 * Structure (matches Minecraft 1.20+ cherry grove):
 *  - Straight trunk 5–7 blocks tall
 *  - 2–3 near-horizontal branches spreading outward from the top 2 trunk blocks
 *  - Each branch ends in a wide, flat leaf canopy (ellipsoid rx=3–4, ry=1–2)
 *  - Leaf fringe hangs 1–3 blocks below the outer edge of each canopy
 *  - Small cap of leaves directly on the trunk top
 */
class CherryTree extends GenericTree{

	public function __construct(Random $random, BlockTransaction $transaction){
		parent::__construct($random, $transaction);
		$this->setHeight($random->nextBoundedInt(3) + 5); // 5–7
		$this->setType(VanillaBlocks::CHERRY_LOG(), VanillaBlocks::CHERRY_LEAVES());
	}

	public function canPlace(int $base_x, int $base_y, int $base_z, ChunkManager $world) : bool{
		$worldMaxY = $world->getMaxY();
		$trunkTop  = $base_y + $this->height - 1;

		for($y = $base_y; $y <= $trunkTop + 5; ++$y){
			if($y < 0 || $y >= $worldMaxY){
				return false;
			}
			// Trunk column only below canopy, wider above
			$r = ($y <= $trunkTop) ? 1 : 5;
			for($x = $base_x - $r; $x <= $base_x + $r; ++$x){
				for($z = $base_z - $r; $z <= $base_z + $r; ++$z){
					if(!isset($this->overridables[$world->getBlockAt($x, $y, $z)->getTypeId()])){
						return false;
					}
				}
			}
		}
		return true;
	}

	public function generate(ChunkManager $world, Random $random, int $source_x, int $source_y, int $source_z) : bool{
		if($this->cannotGenerateAt($source_x, $source_y, $source_z, $world)){
			return false;
		}

		$trunkTop = $source_y + $this->height - 1;

		// ── Trunk ──────────────────────────────────────────────────────────────
		for($dy = 0; $dy < $this->height; ++$dy){
			$this->replaceIfAirOrLeaves($source_x, $source_y + $dy, $source_z, $this->log_type, $world);
		}

		// ── Branches ───────────────────────────────────────────────────────────
		// 2–3 branches, nearly horizontal (rise only 1 block over 3–4 lateral)
		$branchCount = $random->nextBoundedInt(2) + 2;
		$baseAngles  = [];
		for($i = 0; $i < $branchCount; ++$i){
			$baseAngles[] = ($i * 360 / $branchCount) + $random->nextBoundedInt(40) - 20;
		}

		foreach($baseAngles as $angle){
			$rad       = deg2rad($angle);
			$branchLen = $random->nextBoundedInt(2) + 3; // 3–4 blocks lateral

			// Branch starts from one of the top 2 trunk blocks
			$branchBaseY = $trunkTop - $random->nextBoundedInt(2);

			for($step = 1; $step <= $branchLen; ++$step){
				$dx = (int) round(cos($rad) * $step);
				$dz = (int) round(sin($rad) * $step);
				// Rise only 1 block total across the whole branch (very flat)
				$dy = ($step >= $branchLen) ? 1 : 0;

				$bx = $source_x + $dx;
				$by = $branchBaseY + $dy;
				$bz = $source_z + $dz;

				$this->replaceIfAirOrLeaves($bx, $by, $bz, $this->log_type, $world);

				// Leaf canopy at branch tip
				if($step === $branchLen){
					$rx = $random->nextBoundedInt(2) + 3; // 3–4 horizontal
					$this->placeCanopyCluster($world, $random, $bx, $by, $bz, $rx);
				}
			}
		}

		// Small cap on trunk top
		$this->placeCanopyCluster($world, $random, $source_x, $trunkTop + 1, $source_z, 2);

		$this->transaction->addBlockAt($source_x, $source_y - 1, $source_z, VanillaBlocks::DIRT());
		return true;
	}

	/**
	 * Wide flat canopy cluster with hanging fringe.
	 *
	 * @param int $rx horizontal radius (3–4 for branch tips, 2 for trunk cap)
	 */
	private function placeCanopyCluster(
		ChunkManager $world, Random $random,
		int $cx, int $cy, int $cz,
		int $rx
	) : void{
		$ry = 2; // vertical half-height — flat ellipsoid

		// Fill ellipsoid
		for($dx = -$rx; $dx <= $rx; ++$dx){
			for($dz = -$rx; $dz <= $rx; ++$dz){
				for($dy = -$ry; $dy <= $ry; ++$dy){
					$nx   = $dx / $rx;
					$ny   = $dy / $ry;
					$nz   = $dz / $rx;
					$dist = $nx * $nx + $ny * $ny + $nz * $nz;

					if($dist > 1.0){
						continue;
					}
					// Randomly skip outer-edge blocks → irregular, blobby look
					if($dist > 0.72 && $random->nextBoundedInt(3) === 0){
						continue;
					}

					$this->replaceIfAirOrLeaves($cx + $dx, $cy + $dy, $cz + $dz, $this->leaves_type, $world);
				}

				// Hanging fringe below the outer ring
				$hd = sqrt($dx * $dx + $dz * $dz);
				if($hd >= $rx - 1.2 && $hd <= $rx + 0.6){
					// 1–3 hanging leaf blocks, weighted toward shorter
					$maxHang = $random->nextBoundedInt(3) + 1; // 1–3
					for($h = 1; $h <= $maxHang; ++$h){
						// Each extra block has decreasing probability
						if($h > 1 && $random->nextBoundedInt(2) === 0){
							break;
						}
						$this->replaceIfAirOrLeaves($cx + $dx, $cy - $ry - $h, $cz + $dz, $this->leaves_type, $world);
					}
				}
			}
		}
	}

	/**
	 * Shared radii table used by the sapling-growth object.
	 *
	 * @return int[]
	 * @phpstan-return array<int, int>
	 */
	public static function layerRadiiByTrunkDelta() : array{
		return [
			-2 => 2,
			-1 => 3,
			0  => 3,
			1  => 2,
			2  => 1,
		];
	}
}
