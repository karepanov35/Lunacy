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
use pocketmine\world\generator\object\DoubleTallPlant;
use pocketmine\world\generator\object\Flower;
use pocketmine\world\generator\object\TallGrass;
use pocketmine\world\generator\overworld\biome\BiomeIds;
use pocketmine\world\generator\utils\SurfacePlacementUtils;
use function count;

class PlainsPopulator extends BiomePopulator{

	/** @var Block[] */
	protected static array $PLAINS_FLOWERS;

	/** @var Block[] */
	protected static array $PLAINS_TULIPS;

	public static function init() : void{
		parent::init();

		self::$PLAINS_FLOWERS = [
			VanillaBlocks::POPPY(),
			VanillaBlocks::AZURE_BLUET(),
			VanillaBlocks::OXEYE_DAISY()
		];

		self::$PLAINS_TULIPS = [
			VanillaBlocks::RED_TULIP(),
			VanillaBlocks::ORANGE_TULIP(),
			VanillaBlocks::WHITE_TULIP(),
			VanillaBlocks::PINK_TULIP()
		];
	}

	private OctaveGenerator $noise_gen;

	public function __construct(){
		parent::__construct();
		$this->noise_gen = SimplexOctaveGenerator::fromRandomAndOctaves(new Random(2345), 1, 0, 0, 0);
		$this->noise_gen->setScale(1 / 200.0);
	}

	protected function initPopulators() : void{
		$this->flower_decorator->setAmount(0);
		$this->tall_grass_decorator->setAmount(0);
	}

	public function getBiomes() : ?array{
		return [BiomeIds::PLAINS];
	}

	public function populateOnGround(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$source_x = $chunk_x << Chunk::COORD_BIT_SIZE;
		$source_z = $chunk_z << Chunk::COORD_BIT_SIZE;

		$flower_amount = 4;
		$tall_grass_amount = 2;
		if($this->noise_gen->noise($source_x + 8, $source_z + 8, 0, 0.5, 2.0, false) >= -0.8){
			$flower_amount = 2;
			$tall_grass_amount = 4;
			for($i = 0; $i < 3; ++$i){
				$x = $source_x + $random->nextBoundedInt(16);
				$z = $source_z + $random->nextBoundedInt(16);
				$y = SurfacePlacementUtils::getSurfaceYForSoil($world, $x, $z, BlockTypeIds::GRASS);
				if($y !== null){
					(new DoubleTallPlant(VanillaBlocks::DOUBLE_TALLGRASS()))->generate($world, $random, $x, $y, $z);
				}
			}
		}

		$flower = match(true){
			$this->noise_gen->noise($source_x + 8, $source_z + 8, 0, 0.5, 2.0, false) < -0.8 => self::$PLAINS_TULIPS[$random->nextBoundedInt(count(self::$PLAINS_TULIPS))],
			$random->nextBoundedInt(3) > 0 => self::$PLAINS_FLOWERS[$random->nextBoundedInt(count(self::$PLAINS_FLOWERS))],
			default => VanillaBlocks::DANDELION()
		};

		for($i = 0; $i < $flower_amount; ++$i){
			$x = $source_x + $random->nextBoundedInt(16);
			$z = $source_z + $random->nextBoundedInt(16);
			$y = SurfacePlacementUtils::getSurfaceYForSoil($world, $x, $z, BlockTypeIds::GRASS);
			if($y !== null){
				(new Flower($flower))->generate($world, $random, $x, $y, $z);
			}
		}

		for($i = 0; $i < $tall_grass_amount; ++$i){
			$x = $source_x + $random->nextBoundedInt(16);
			$z = $source_z + $random->nextBoundedInt(16);
			$y = SurfacePlacementUtils::getSurfaceYForSoil($world, $x, $z, BlockTypeIds::GRASS, BlockTypeIds::DIRT);
			if($y !== null){
				(new TallGrass(VanillaBlocks::TALL_GRASS()))->generate($world, $random, $x, $y, $z);
			}
		}

		parent::populateOnGround($world, $random, $chunk_x, $chunk_z, $chunk);
	}
}

PlainsPopulator::init();
