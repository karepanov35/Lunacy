<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\decorator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Decorator;
use pocketmine\world\generator\noise\glowstone\PerlinOctaveGenerator;
use function count;
use function cos;
use function deg2rad;
use function floor;
use function sin;

class SurfaceCaveDecorator extends Decorator{

	private const BORDER_INSET = 4;

	public function decorate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		if($random->nextBoundedInt(12) !== 0){
			return;
		}

		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;

		// Keep openings away from chunk borders so halves never disagree across seams.
		$start_cx = self::BORDER_INSET + $random->nextBoundedInt(16 - self::BORDER_INSET * 2);
		$start_cz = self::BORDER_INSET + $random->nextBoundedInt(16 - self::BORDER_INSET * 2);
		$start_y = $chunk->getHighestBlockAt($start_cx, $start_cz);
		if($start_y <= 4 || $start_y > 128){
			return;
		}

		$octaves = PerlinOctaveGenerator::fromRandomAndOctaves($random, 3, 4, 2, 4);
		$noise = $octaves->getFractalBrownianMotion($cx, $cz, 0, 0.5, 0.2);
		$angles = [];
		for($i = 0, $noise_c = count($noise); $i < $noise_c; ++$i){
			$angles[$i] = 360.0 * $noise[$i];
		}
		$section_count = (int) (count($angles) / 2);
		$nodes = [];
		$current_node = new Vector3($cx + $start_cx, $start_y, $cz + $start_cz);
		$nodes[] = $current_node;
		$length = 5;
		for($i = 0; $i < $section_count; ++$i){
			$yaw = $angles[$i + $section_count];
			$delta_y = -abs((int) floor($noise[$i] * $length));
			$delta_x = (int) floor((float) $length * cos(deg2rad($yaw)));
			$delta_z = (int) floor((float) $length * sin(deg2rad($yaw)));
			$current_node = $current_node->add($delta_x, $delta_y, $delta_z);
			$nodes[] = $current_node->floor();
		}
		foreach($nodes as $node){
			if($node->y < 4){
				continue;
			}

			$this->caveAroundRay($world, $node, $random, $cx, $cz);
		}
	}

	private function caveAroundRay(ChunkManager $world, Vector3 $block, Random $random, int $chunkMinX, int $chunkMinZ) : void{
		$radius = $random->nextBoundedInt(2) + 1;
		$block_x = (int) $block->x;
		$block_y = (int) $block->y;
		$block_z = (int) $block->z;
		$carveMinX = $chunkMinX + self::BORDER_INSET;
		$carveMaxX = $chunkMinX + Chunk::COORD_MASK - self::BORDER_INSET;
		$carveMinZ = $chunkMinZ + self::BORDER_INSET;
		$carveMaxZ = $chunkMinZ + Chunk::COORD_MASK - self::BORDER_INSET;
		$stone = BlockTypeIds::STONE;
		$dirt = BlockTypeIds::DIRT;
		$grass = BlockTypeIds::GRASS;
		$gravel = BlockTypeIds::GRAVEL;

		for($x = $block_x - $radius; $x <= $block_x + $radius; ++$x){
			if($x < $carveMinX || $x > $carveMaxX){
				continue;
			}
			for($y = $block_y - $radius; $y <= $block_y + $radius; ++$y){
				for($z = $block_z - $radius; $z <= $block_z + $radius; ++$z){
					if($z < $carveMinZ || $z > $carveMaxZ){
						continue;
					}
					$distance_squared = ($block_x - $x) * ($block_x - $x) + ($block_y - $y) * ($block_y - $y) + ($block_z - $z) * ($block_z - $z);
					if($distance_squared >= $radius * $radius){
						continue;
					}
					$typeId = $world->getBlockAt($x, $y, $z)->getTypeId();
					if($typeId === $stone || $typeId === $dirt || $typeId === $grass || $typeId === $gravel){
						$world->setBlockAt($x, $y, $z, VanillaBlocks::AIR());
					}
				}
			}
		}
	}
}
