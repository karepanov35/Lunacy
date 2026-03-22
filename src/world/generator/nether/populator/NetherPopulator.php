<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator;

use pocketmine\world\generator\nether\decorator\FireDecorator;
use pocketmine\world\generator\nether\decorator\MushroomDecorator;
use pocketmine\world\generator\nether\populator\lumi\BasaltDeltaLavaPopulator;
use pocketmine\world\generator\nether\populator\lumi\BasaltDeltaMagmaPopulator;
use pocketmine\world\generator\nether\populator\lumi\BasaltDeltaPillarPopulator;
use pocketmine\world\generator\nether\populator\lumi\CrimsonFungiTreePopulator;
use pocketmine\world\generator\nether\populator\lumi\CrimsonGrassesPopulator;
use pocketmine\world\generator\nether\populator\lumi\LumiGlowstonePopulator;
use pocketmine\world\generator\nether\populator\lumi\LumiNetherBiomePicker;
use pocketmine\world\generator\nether\populator\lumi\LumiSimpleLavaPopulator;
use pocketmine\world\generator\nether\populator\lumi\NetherBiomeSurfacePopulator;
use pocketmine\world\generator\nether\populator\lumi\NetherFortressPopulatorStub;
use pocketmine\world\generator\nether\populator\lumi\SoulsandFossilPopulator;
use pocketmine\world\generator\nether\populator\lumi\SoulSoilMixPopulator;
use pocketmine\world\generator\nether\populator\lumi\WarpedFungiTreePopulator;
use pocketmine\world\generator\nether\populator\lumi\WarpedGrassesPopulator;
use pocketmine\world\generator\nether\populator\AncientDebrisPopulator;
use pocketmine\world\generator\VanillaPopulator;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

/**
 * Нижний мир в духе Lumi: биомы (шум), поверхность, популяторы из Lumi.
 * Крепость Незера — заглушка (см. {@see NetherFortressPopulatorStub}).
 */
class NetherPopulator implements VanillaPopulator{

	/** @var VanillaPopulator[] */
	private array $in_ground_populators = [];

	/** @var VanillaPopulator[] */
	private array $on_ground_populators = [];

	public function __construct(int $world_height = World::Y_MAX, int $world_seed = 0){
		$picker = new LumiNetherBiomePicker($world_seed);

		$this->in_ground_populators = [
			new NetherBiomeSurfacePopulator($picker),
			new LumiSimpleLavaPopulator(),
			new OrePopulator($world_height),
			new AncientDebrisPopulator(),
			new BasaltDeltaLavaPopulator(),
			new BasaltDeltaMagmaPopulator(),
			new BasaltDeltaPillarPopulator(),
			new CrimsonFungiTreePopulator(),
			new CrimsonGrassesPopulator(),
			new WarpedFungiTreePopulator(),
			new WarpedGrassesPopulator(),
			new SoulsandFossilPopulator(),
			new SoulSoilMixPopulator($picker),
			new LumiGlowstonePopulator(),
			new NetherFortressPopulatorStub(),
		];

		$this->on_ground_populators = [
			new FireDecorator(),
			new MushroomDecorator(VanillaBlocks::BROWN_MUSHROOM()),
			new MushroomDecorator(VanillaBlocks::RED_MUSHROOM()),
		];

		$this->on_ground_populators[0]->setAmount(1);
		$this->on_ground_populators[1]->setAmount(1);
		$this->on_ground_populators[2]->setAmount(1);
	}

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$this->populateInGround($world, $random, $chunk_x, $chunk_z, $chunk);
		$this->populateOnGround($world, $random, $chunk_x, $chunk_z, $chunk);
	}

	private function populateInGround(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		foreach($this->in_ground_populators as $populator){
			$populator->populate($world, $random, $chunk_x, $chunk_z, $chunk);
		}
	}

	private function populateOnGround(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		foreach($this->on_ground_populators as $populator){
			$populator->populate($world, $random, $chunk_x, $chunk_z, $chunk);
		}
	}
}
