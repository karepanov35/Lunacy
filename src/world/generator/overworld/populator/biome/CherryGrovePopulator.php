<?php

declare(strict_types=1);

namespace pocketmine\world\generator\overworld\populator\biome;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeTags;
use pocketmine\block\Leaves;
use pocketmine\block\Liquid;
use pocketmine\block\PinkPetals;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\object\tree\CherryTree;
use pocketmine\world\generator\overworld\biome\BiomeIds;
use pocketmine\world\generator\overworld\decorator\types\DoublePlantDecoration;
use pocketmine\world\generator\overworld\decorator\types\FlowerDecoration;
use pocketmine\world\generator\overworld\decorator\types\TreeDecoration;

class CherryGrovePopulator extends BiomePopulator{

	/** @var int[] */
	private static array $PINK_PETAL_FACINGS = [Facing::NORTH, Facing::SOUTH, Facing::EAST, Facing::WEST];

	protected static function initTrees() : void{
		self::$TREES = [
			new TreeDecoration(CherryTree::class, 10)
		];
	}

	protected static function initFlowers() : void{
		// Розовые лепестки ставим вручную с count 1–4 (см. {@see tryPlacePinkPetalsCluster});
		self::$FLOWERS = [
			new FlowerDecoration(VanillaBlocks::DANDELION(), 3),
			new FlowerDecoration(VanillaBlocks::POPPY(), 3)
		];
	}

	protected function initPopulators() : void{
		parent::initPopulators();

		$this->tree_decorator->setAmount(4);
		$this->tree_decorator->setTrees(...self::$TREES);

		$this->flower_decorator->setAmount(4);
		$this->flower_decorator->setFlowers(...self::$FLOWERS);

		$this->tall_grass_decorator->setAmount(8);

		// Высокая трава (double tallgrass) — как в ванильной вишнёвой роще
		$this->double_plant_decorator->setAmount(5);
		$this->double_plant_decorator->setDoublePlants(
			new DoublePlantDecoration(VanillaBlocks::DOUBLE_TALLGRASS(), 8),
			new DoublePlantDecoration(VanillaBlocks::LARGE_FERN(), 2)
		);

		$this->dead_bush_decorator->setAmount(0);
		$this->cactus_decorator->setAmount(0);

		// Отключаем озёра — они создают ямы в вишнёвой роще
		$this->water_lake_decorator->setAmount(0);
		$this->lava_lake_decorator->setAmount(0);
	}

	public function getBiomes() : ?array{
		return [BiomeIds::CHERRY_GROVE];
	}

	public function populateOnGround(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		parent::populateOnGround($world, $random, $chunk_x, $chunk_z, $chunk);

		$source_x = $chunk_x << Chunk::COORD_BIT_SIZE;
		$source_z = $chunk_z << Chunk::COORD_BIT_SIZE;

		for($i = 0; $i < 96; ++$i){
			$this->tryPlacePinkPetalsCluster($world, $random, $chunk, $source_x, $source_z);
		}
	}

	private function tryPlacePinkPetalsCluster(ChunkManager $world, Random $random, Chunk $chunk, int $source_x, int $source_z) : void{
		$x = $random->nextBoundedInt(16);
		$z = $random->nextBoundedInt(16);
		$wx = $source_x + $x;
		$wz = $source_z + $z;
		$seatY = $this->findPinkPetalsSeatY($world, $chunk, $x, $z, $wx, $wz);
		if($seatY === null){
			return;
		}
		$placeY = $seatY + 1;
		$above = $world->getBlockAt($wx, $placeY, $wz);
		if(!$above->canBeReplaced() && $above->getTypeId() !== BlockTypeIds::AIR){
			return;
		}

		$facing = self::$PINK_PETAL_FACINGS[$random->nextBoundedInt(4)];
		$count = $random->nextBoundedInt(PinkPetals::MAX_COUNT) + PinkPetals::MIN_COUNT;
		$petals = VanillaBlocks::PINK_PETALS()->setFacing($facing)->setCount($count);
		$world->setBlockAt($wx, $placeY, $wz, $petals);
	}

	private function findPinkPetalsSeatY(ChunkManager $world, Chunk $chunk, int $lx, int $lz, int $wx, int $wz) : ?int{
		$probeTop = min($chunk->getHighestBlockAt($lx, $lz), $world->getMaxY() - 2);
		$minY = max($world->getMinY() + 1, $probeTop - 72);

		for($y = $probeTop; $y >= $minY; --$y){
			$b = $world->getBlockAt($wx, $y, $wz);
			if($b instanceof PinkPetals || $b instanceof Leaves){
				continue;
			}
			// Не ставим лепестки на воду или жидкость
			if($b instanceof Liquid){
				return null;
			}
			if(!$b->hasTypeTag(BlockTypeTags::DIRT) && !$b->hasTypeTag(BlockTypeTags::MUD)){
				continue;
			}
			$up = $world->getBlockAt($wx, $y + 1, $wz);
			// Блок над грунтом должен быть воздухом
			if($up->getTypeId() !== BlockTypeIds::AIR){
				continue;
			}
			return $y;
		}

		return null;
	}
}

CherryGrovePopulator::init();
