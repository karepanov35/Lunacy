<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator\lumi;

use pocketmine\world\generator\VanillaPopulator;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

/**
 * Заглушка под {@code PopulatorNetherFortress} (Lumi).
 * Полная генерация крепостей требует системы структур Nukkit; в PocketMine не портируется.
 */
final class NetherFortressPopulatorStub implements VanillaPopulator{

	public function populate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		// намеренно пусто
	}
}
