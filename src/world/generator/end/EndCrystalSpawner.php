<?php

declare(strict_types=1);

namespace pocketmine\world\generator\end;

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Location;
use pocketmine\entity\object\EndCrystal;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\World;
use function strtolower;

final class EndCrystalSpawner{

	public static function trySpawnForPopulatedChunk(World $world, int $chunkX, int $chunkZ) : void{
		$generatorName = strtolower($world->getProvider()->getWorldData()->getGenerator());
		$entry = GeneratorManager::getInstance()->getGenerator($generatorName);
		if($entry === null || $entry->getGeneratorClass() !== TheEndGenerator::class){
			return;
		}

		foreach(EndObsidianPillar::computeAll($world->getSeed()) as $pillar){
			$x = $pillar->centerX;
			$z = $pillar->centerZ;
			if(($x >> Chunk::COORD_BIT_SIZE) !== $chunkX || ($z >> Chunk::COORD_BIT_SIZE) !== $chunkZ){
				continue;
			}

			$crystalY = $pillar->height + 1;
			if($world->getBlockAt($x, $pillar->height, $z)->getTypeId() !== BlockTypeIds::BEDROCK){
				continue;
			}

			$bb = AxisAlignedBB::one()->offset($x + 0.5, $crystalY, $z + 0.5);
			foreach($world->getNearbyEntities($bb) as $entity){
				if($entity instanceof EndCrystal){
					continue 2;
				}
			}

			$crystal = new EndCrystal(new Location($x + 0.5, (float) $crystalY, $z + 0.5, $world, 0.0, 0.0));
			$crystal->setShowBase(true);
			$crystal->spawnToAll();
		}
	}
}
