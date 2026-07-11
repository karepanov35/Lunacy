<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\populator\biome;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\noise\bukkit\OctaveGenerator;
use pocketmine\world\generator\noise\glowstone\SimplexOctaveGenerator;
use pocketmine\world\generator\object\Flower;
use pocketmine\world\generator\overworld\biome\BiomeIds;
use pocketmine\world\generator\utils\SurfacePlacementUtils;
use function count;

class FlowerForestPopulator extends ForestPopulator{

	/** @var Block[] */
	private static array $FOREST_FLOWERS;

	protected static function initFlowers() : void{
		self::$FOREST_FLOWERS = [
			VanillaBlocks::POPPY(),
			VanillaBlocks::POPPY(),
			VanillaBlocks::DANDELION(),
			VanillaBlocks::ALLIUM(),
			VanillaBlocks::AZURE_BLUET(),
			VanillaBlocks::RED_TULIP(),
			VanillaBlocks::ORANGE_TULIP(),
			VanillaBlocks::WHITE_TULIP(),
			VanillaBlocks::PINK_TULIP(),
			VanillaBlocks::OXEYE_DAISY()
		];
	}

	private OctaveGenerator $noise_gen;

	protected function initPopulators() : void{
		parent::initPopulators();
		$this->tree_decorator->setAmount(6);
		$this->flower_decorator->setAmount(0);
		$this->double_plant_lowering_amount = 1;
		$this->noise_gen = SimplexOctaveGenerator::fromRandomAndOctaves(new Random(2345), 1, 0, 0, 0);
		$this->noise_gen->setScale(1 / 48.0);
	}

	public function getBiomes() : ?array{
		return [BiomeIds::FLOWER_FOREST];
	}

	public function populateOnGround(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		parent::populateOnGround($world, $random, $chunk_x, $chunk_z, $chunk);

		$source_x = $chunk_x << Chunk::COORD_BIT_SIZE;
		$source_z = $chunk_z << Chunk::COORD_BIT_SIZE;

		for($i = 0; $i < 20; ++$i){
			$x = $source_x + $random->nextBoundedInt(16);
			$z = $source_z + $random->nextBoundedInt(16);
			$y = SurfacePlacementUtils::getSurfaceYForSoil($world, $x, $z, BlockTypeIds::GRASS);
			if($y === null){
				continue;
			}
			$noise = ($this->noise_gen->noise($x, $z, 0.5, 0, 2.0, false) + 1.0) / 2.0;
			$noise = $noise < 0 ? 0 : ($noise > 0.9999 ? 0.9999 : $noise);
			$flower = self::$FOREST_FLOWERS[(int) ($noise * count(self::$FOREST_FLOWERS))];
			(new Flower($flower))->generate($world, $random, $x, $y, $z);
		}
	}
}

FlowerForestPopulator::init();