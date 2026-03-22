<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\generator\VanillaPopulator;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/**
 * Древние обломки как в Java 1.16+: два «прохода» на чанк — мелкая жила 8–22 (1–3 блока)
 * и редкая рассеянная жила 8–верх мира (1–2 блока). Без лишних ограничений соседства, как у рудной жилы PM.
 */
final class AncientDebrisPopulator implements VanillaPopulator{

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$maxY = $world->getMaxY();
		$top = min(119, $maxY - 2);
		if($top < 8){
			return;
		}

		// Малая жила (чаще встречается): 1–3 блока, Y 8–22
		$smallSize = $random->nextBoundedInt(3) + 1;
		$this->placeVein($world, $random, $chunk_x, $chunk_z, 8, 22, $smallSize);

		// Крупный диапазон: 1–2 блока, Y 8–119 (адаптировано к высоте мира)
		$largeSize = $random->nextBoundedInt(2) + 1;
		$this->placeVein($world, $random, $chunk_x, $chunk_z, 8, $top, $largeSize);
	}

	private function placeVein(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, int $minY, int $maxY, int $blockCount) : void{
		if($maxY < $minY || $blockCount <= 0){
			return;
		}

		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;

		$x = $cx + $random->nextBoundedInt(16);
		$y = $minY + $random->nextBoundedInt($maxY - $minY + 1);
		$z = $cz + $random->nextBoundedInt(16);

		$debris = VanillaBlocks::ANCIENT_DEBRIS();
		$lava = BlockTypeIds::LAVA;

		for($i = 0; $i < $blockCount; ++$i){
			if($this->tryReplaceNetherrack($world, $x, $y, $z, $debris, $lava)){
				// Смещение следующего блока жилы (короткая «цепочка» как у ванильной жилы)
				$x += $random->nextBoundedInt(3) - 1;
				$y += $random->nextBoundedInt(3) - 1;
				$z += $random->nextBoundedInt(3) - 1;
				$y = max($minY, min($maxY, $y));
			}else{
				$x = $cx + $random->nextBoundedInt(16);
				$y = $minY + $random->nextBoundedInt($maxY - $minY + 1);
				$z = $cz + $random->nextBoundedInt(16);
			}
		}
	}

	private function tryReplaceNetherrack(ChunkManager $world, int $x, int $y, int $z, \pocketmine\block\Block $debris, int $lavaId) : bool{
		$here = $world->getBlockAt($x, $y, $z);
		if($here->getTypeId() !== BlockTypeIds::NETHERRACK){
			return false;
		}
		foreach([
			[$x, $y, $z - 1], [$x, $y, $z + 1], [$x - 1, $y, $z], [$x + 1, $y, $z],
			[$x, $y - 1, $z], [$x, $y + 1, $z],
		] as [$nx, $ny, $nz]){
			$id = $world->getBlockAt($nx, $ny, $nz)->getTypeId();
			if($id === $lavaId){
				return false;
			}
		}
		$world->setBlockAt($x, $y, $z, $debris);
		return true;
	}
}
