<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator;

use pocketmine\world\generator\VanillaPopulator;
use pocketmine\world\generator\object\OreType;
use pocketmine\world\generator\object\OreVein;
use pocketmine\world\generator\overworld\populator\biome\utils\OreTypeHolder;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

/**
 * Руды Нижнего мира (в духе Lumi + ваниль): кварц, золото, магма, гравий, песок душ. Древние обломки — {@see AncientDebrisPopulator}.
 */
class OrePopulator implements VanillaPopulator{

	/** @var OreTypeHolder[] */
	private array $ores = [];

	public function __construct(int $world_height = World::Y_MAX){
		$minY = 10;
		$maxY = min(117, max($minY + 5, $world_height - 11));

		// Кварц — как раньше, чуть подстраиваем верх под высоту мира
		$this->addOre(new OreType(
			VanillaBlocks::NETHER_QUARTZ_ORE(),
			$minY,
			$maxY,
			13,
			BlockTypeIds::NETHERRACK
		), 16);

		// Незерская золотая руда (основной запрос): y ~10–117, жилы среднего размера, частые попытки
		$this->addOre(new OreType(
			VanillaBlocks::NETHER_GOLD_ORE(),
			$minY,
			$maxY,
			4,
			BlockTypeIds::NETHERRACK
		), 20);

		// Магма
		$magTop = min(32 + (5 * ($world_height >> 7)), $maxY);
		if($magTop > 26){
			$this->addOre(new OreType(
				VanillaBlocks::MAGMA(),
				26,
				$magTop,
				32,
				BlockTypeIds::NETHERRACK
			), 16);
		}

		// Древние обломки: см. {@see AncientDebrisPopulator} (ванильные два прохода на чанк)

		// Гравий в незераке
		$this->addOre(new OreType(
			VanillaBlocks::GRAVEL(),
			5,
			min(105, $maxY),
			15,
			BlockTypeIds::NETHERRACK
		), 10);

		// Вкрапления песка душ в незераке
		$this->addOre(new OreType(
			VanillaBlocks::SOUL_SAND(),
			30,
			min(105, $maxY),
			15,
			BlockTypeIds::NETHERRACK
		), 8);
	}

	protected function addOre(OreType $type, int $value) : void{
		$this->ores[] = new OreTypeHolder($type, $value);
	}

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;

		foreach($this->ores as $ore_type_holder){
			for($n = 0; $n < $ore_type_holder->value; ++$n){
				$source_x = $cx + $random->nextBoundedInt(16);
				$source_z = $cz + $random->nextBoundedInt(16);
				$source_y = $ore_type_holder->type->getRandomHeight($random);
				(new OreVein($ore_type_holder->type))->generate($world, $random, $source_x, $source_y, $source_z);
			}
		}
	}
}
