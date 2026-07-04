<?php


/*
 *
 *
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GPL-2.0 license as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Karepanov
 * @link https://github.com/karepanov35/Lunacy
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\world\generator\end;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\tile\EndGateway as TileEndGateway;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\entity\Location;
use pocketmine\entity\mob\EnderDragon;
use pocketmine\entity\object\EndCrystal;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\end\populator\ChorusTreeGenerator;
use pocketmine\world\generator\end\populator\EndExitPortalStructure;
use pocketmine\world\generator\end\populator\EndGatewayStructure;
use pocketmine\world\World;
use function array_keys;
use function abs;
use function count;
use function cos;
use function fmod;
use function ksort;
use function max;
use function min;
use function sin;
use function sqrt;

class TheEndGenerator extends Generator{

	private const COORDINATE_SCALE = 684.412;
	private const DETAIL_NOISE_SCALE_X = 80.0;
	private const DETAIL_NOISE_SCALE_Z = 80.0;

	/** @var float[][][] */
	private array $density = [];

	/** @var float[]|null */
	private ?array $detailNoise = null;
	/** @var float[]|null */
	private ?array $roughnessNoise = null;
	/** @var float[]|null */
	private ?array $roughnessNoise2 = null;

	private NoiseGeneratorOctavesD $roughnessNoiseOctaves;
	private NoiseGeneratorOctavesD $roughnessNoiseOctaves2;
	private NoiseGeneratorOctavesD $detailNoiseOctaves;
	private EndIslandNoise $islandNoise;

	private int $localSeed1;
	private int $localSeed2;

	/** @var array<int, array{centerX: int, centerZ: int, radius: int, height: int, guarded: bool}> */
	private array $obsidianPillars;

	private readonly ChorusTreeGenerator $chorusTreeGenerator;
	private readonly EndGatewayStructure $endGatewayStructure;
	private readonly EndExitPortalStructure $endExitPortalStructure;

	private const EXIT_PORTAL_CENTER_X = 0;
	private const EXIT_PORTAL_CENTER_Z = 0;

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);
		$r = new Random($seed);
		$this->localSeed1 = $r->nextInt();
		$this->localSeed2 = $r->nextInt();
		$this->roughnessNoiseOctaves = new NoiseGeneratorOctavesD($r, 16);
		$this->roughnessNoiseOctaves2 = new NoiseGeneratorOctavesD($r, 16);
		$this->detailNoiseOctaves = new NoiseGeneratorOctavesD($r, 8);
		$this->islandNoise = new EndIslandNoise($r);

		$this->obsidianPillars = self::computeObsidianPillars($seed);
		$this->chorusTreeGenerator = new ChorusTreeGenerator();
		$this->endGatewayStructure = new EndGatewayStructure();
		$this->endExitPortalStructure = new EndExitPortalStructure();
	}

	/**
	 * @return array<int, array{centerX: int, centerZ: int, radius: int, height: int, guarded: bool}>
	 */
	public static function getObsidianPillars(int $worldSeed) : array{
		return self::computeObsidianPillars($worldSeed);
	}

	/**
	 * @return array<int, array{centerX: int, centerZ: int, radius: int, height: int, guarded: bool}>
	 */
	private static function computeObsidianPillars(int $worldSeed) : array{
		$key = (new Random($worldSeed))->nextInt() & 0xffff;
		$shuffle = new Random($key);
		$pillars = range(0, 9);
		for($i = 9; $i > 0; --$i){
			$j = $shuffle->nextBoundedInt($i + 1);
			[$pillars[$i], $pillars[$j]] = [$pillars[$j], $pillars[$i]];
		}
		$result = [];
		for($i = 0; $i < 10; ++$i){
			$pillar = $pillars[$i];
			$result[] = [
				"centerX" => (int) (42 * cos(2 * (-M_PI + (M_PI / 10) * $i))),
				"centerZ" => (int) (42 * sin(2 * (-M_PI + (M_PI / 10) * $i))),
				"radius" => 2 + intdiv($pillar, 3),
				"height" => 76 + $pillar * 3,
				"guarded" => $pillar === 1 || $pillar === 2,
			];
		}

		return $result;
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(($chunkX * $this->localSeed1 ^ $chunkZ * $this->localSeed2 ^ $this->seed) & 0x7fffffffffffffff);

		$chunk = new Chunk([], false);
		$biomePalette = new PalettedBlockArray(BiomeIds::THE_END);
		foreach($chunk->getSubChunks() as $y => $_){
			$chunk->setSubChunk($y, new SubChunk(Block::EMPTY_STATE_ID, [], clone $biomePalette));
		}

		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				for($y = $world->getMinY(); $y < $world->getMaxY(); ++$y){
					$chunk->setBiomeId($x, $y, $z, BiomeIds::THE_END);
				}
			}
		}

		$densityX = $chunkX << 1;
		$densityZ = $chunkZ << 1;

		$cs = self::COORDINATE_SCALE;
		$this->detailNoise = $this->detailNoiseOctaves->generateNoiseOctaves(
			$this->detailNoise,
			$densityX, 0, $densityZ,
			3, 33, 3,
			($cs * 2) / self::DETAIL_NOISE_SCALE_X,
			4.277575000000001,
			($cs * 2) / self::DETAIL_NOISE_SCALE_Z
		);
		$this->roughnessNoise = $this->roughnessNoiseOctaves->generateNoiseOctaves(
			$this->roughnessNoise,
			$densityX, 0, $densityZ,
			3, 33, 3,
			$cs * 2, $cs, $cs * 2
		);
		$this->roughnessNoise2 = $this->roughnessNoiseOctaves2->generateNoiseOctaves(
			$this->roughnessNoise2,
			$densityX, 0, $densityZ,
			3, 33, 3,
			$cs * 2, $cs, $cs * 2
		);

		$index = 0;
		for($i = 0; $i < 3; ++$i){
			for($j = 0; $j < 3; ++$j){
				$noiseHeight = $this->getIslandHeight($chunkX, $chunkZ, $i, $j);
				for($k = 0; $k < 33; ++$k){
					$noiseR = $this->roughnessNoise[$index] / 512.0;
					$noiseR2 = $this->roughnessNoise2[$index] / 512.0;
					$noiseD = ($this->detailNoise[$index] / 10.0 + 1.0) / 2.0;
					$dens = $noiseD < 0 ? $noiseR : ($noiseD > 1 ? $noiseR2 : $noiseR + ($noiseR2 - $noiseR) * $noiseD);
					$dens = ($dens - 8.0) + $noiseHeight;
					++$index;
					if($k < 8){
						$lowering = (8 - $k) / 7.0;
						$dens = $dens * (1.0 - $lowering) + $lowering * -30.0;
					}elseif($k > 33 / 2 - 2){
						$lowering = ($k - (33 / 2 - 2)) / 64.0;
						$lowering = self::clampFloat($lowering, 0.0, 1.0);
						$dens = $dens * (1.0 - $lowering) + $lowering * -3000.0;
					}
					$this->density[$i][$j][$k] = $dens;
				}
			}
		}

		$endStone = VanillaBlocks::END_STONE()->getStateId();

		for($i = 0; $i < 2; ++$i){
			for($j = 0; $j < 2; ++$j){
				for($k = 0; $k < 32; ++$k){
					$d1 = $this->density[$i][$j][$k];
					$d2 = $this->density[$i + 1][$j][$k];
					$d3 = $this->density[$i][$j + 1][$k];
					$d4 = $this->density[$i + 1][$j + 1][$k];
					$d5 = ($this->density[$i][$j][$k + 1] - $d1) / 4.0;
					$d6 = ($this->density[$i + 1][$j][$k + 1] - $d2) / 4.0;
					$d7 = ($this->density[$i][$j + 1][$k + 1] - $d3) / 4.0;
					$d8 = ($this->density[$i + 1][$j + 1][$k + 1] - $d4) / 4.0;

					for($l = 0; $l < 4; ++$l){
						$d9 = $d1;
						$d10 = $d3;
						for($m = 0; $m < 8; ++$m){
							$dens = $d9;
							for($n = 0; $n < 8; ++$n){
								$bx = $m + ($i << 3);
								$bz = $n + ($j << 3);
								$by = $l + ($k << 2);
								if($bx < 16 && $bz < 16 && $by >= $world->getMinY() && $by < $world->getMaxY()){
									if($dens > 0){
										$chunk->setBlockStateId($bx, $by, $bz, $endStone);
									}
								}
								$dens += ($d10 - $d9) / 8.0;
							}
							$d9 += ($d2 - $d1) / 8.0;
							$d10 += ($d4 - $d3) / 8.0;
						}
						$d1 += $d5;
						$d3 += $d7;
						$d2 += $d6;
						$d4 += $d8;
					}
				}
			}
		}

		$world->setChunk($chunkX, $chunkZ, $chunk);
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$this->random->setSeed(0xdeadbeef ^ (($chunkX << 8) ^ $chunkZ ^ $this->seed));

		$this->placeEndPlatform($world, $chunkX, $chunkZ);
		$this->placeExitPortal($world, $chunkX, $chunkZ);
		$this->placeEndIslands($world, $chunkX, $chunkZ);
		$this->placeObsidianPillars($world, $chunkX, $chunkZ);
		$this->placeChorusTrees($world, $chunkX, $chunkZ);
		$this->placeEndGateways($world, $chunkX, $chunkZ);
	}

	public static function finalizeChunkPopulation(World $world, int $chunkX, int $chunkZ) : void{
		if($chunkX === 0 && $chunkZ === 0){
			self::ensureExitPortal($world);
			self::ensureEnderDragon($world);
		}
		self::spawnEndCrystals($world, $chunkX, $chunkZ, $world->getSeed());
		self::spawnEndGatewayTiles($world, $chunkX, $chunkZ);
	}

	public static function ensureExitPortal(World $world) : void{
		$chunk = $world->getChunk(0, 0);
		if($chunk === null){
			$world->loadChunk(0, 0);
			$chunk = $world->getChunk(0, 0);
		}
		if($chunk === null){
			return;
		}

		$portalLevels = self::findExitPortalLevels($world);
		if(count($portalLevels) > 0){
			$baseY = $portalLevels[0];
			if(count($portalLevels) > 1){
				self::clearStackedExitPortalArtifacts($world, $baseY + 1);
			}
			(new EndExitPortalStructure())->generate($world, self::EXIT_PORTAL_CENTER_X, $baseY, self::EXIT_PORTAL_CENTER_Z);
		}else{
			$portalY = self::findExitPortalY($world);
			if($portalY === null){
				return;
			}
			(new EndExitPortalStructure())->generate($world, self::EXIT_PORTAL_CENTER_X, $portalY, self::EXIT_PORTAL_CENTER_Z);
		}
	}

	public static function ensureEnderDragon(World $world) : void{
		$searchBox = new AxisAlignedBB(-128, 0, -128, 128, 256, 128);
		foreach($world->getNearbyEntities($searchBox) as $entity){
			if($entity instanceof EnderDragon){
				return;
			}
		}

		$world->loadChunk(0, 0);
		$dragon = new EnderDragon(
			new Location(0.5, 128, 0.5, $world, 0, 0),
			CompoundTag::create()
		);
		$dragon->spawnToAll();
	}

	public static function ensureEndCrystals(World $world) : void{
		$chunks = [];
		foreach(self::computeObsidianPillars($world->getSeed()) as $pillar){
			$chunkX = $pillar["centerX"] >> Chunk::COORD_BIT_SIZE;
			$chunkZ = $pillar["centerZ"] >> Chunk::COORD_BIT_SIZE;
			$chunks[World::chunkHash($chunkX, $chunkZ)] = [$chunkX, $chunkZ];
		}

		foreach($chunks as [$chunkX, $chunkZ]){
			if(!$world->isChunkGenerated($chunkX, $chunkZ)){
				$world->orderChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
					static function() use ($world, $chunkX, $chunkZ) : void{
						self::spawnEndCrystals($world, $chunkX, $chunkZ, $world->getSeed());
					},
					static function() : void{}
				);
				continue;
			}
			$world->loadChunk($chunkX, $chunkZ);
			self::spawnEndCrystals($world, $chunkX, $chunkZ, $world->getSeed());
		}
	}

	private static function findExitPortalLevels(ChunkManager $world) : array{
		$portalType = VanillaBlocks::END_PORTAL()->getTypeId();
		$levels = [];
		for($y = $world->getMinY(); $y < $world->getMaxY(); ++$y){
			for($dx = -1; $dx <= 1; ++$dx){
				for($dz = -1; $dz <= 1; ++$dz){
					if($world->getBlockAt(self::EXIT_PORTAL_CENTER_X + $dx, $y, self::EXIT_PORTAL_CENTER_Z + $dz)->getTypeId() === $portalType){
						$levels[$y] = true;
						continue 3;
					}
				}
			}
		}
		ksort($levels);
		return array_keys($levels);
	}

	private static function findExitPortalY(World $world) : ?int{
		$chunk = $world->getChunk(0, 0);
		if($chunk === null){
			$world->loadChunk(0, 0);
			$chunk = $world->getChunk(0, 0);
		}
		if($chunk === null){
			return null;
		}

		$y = $chunk->getHighestBlockAt(0, 0);
		if($y === null || $y < $world->getMinY()){
			$y = 60;
		}

		return $y;
	}

	private static function clearStackedExitPortalArtifacts(World $world, int $startY) : void{
		$air = VanillaBlocks::AIR();
		$removableIds = [
			VanillaBlocks::END_PORTAL()->getTypeId() => true,
			VanillaBlocks::BEDROCK()->getTypeId() => true,
			VanillaBlocks::END_STONE()->getTypeId() => true,
			VanillaBlocks::TORCH()->getTypeId() => true,
		];

		for($y = $startY; $y < $world->getMaxY(); ++$y){
			for($dx = -3; $dx <= 3; ++$dx){
				for($dz = -3; $dz <= 3; ++$dz){
					$x = self::EXIT_PORTAL_CENTER_X + $dx;
					$z = self::EXIT_PORTAL_CENTER_Z + $dz;
					$typeId = $world->getBlockAt($x, $y, $z)->getTypeId();
					if(isset($removableIds[$typeId])){
						$world->setBlockAt($x, $y, $z, $air);
					}
				}
			}
		}
	}

	private function placeExitPortal(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		if($chunkX !== 0 || $chunkZ !== 0){
			return;
		}

		$existingPortalLevels = self::findExitPortalLevels($world);
		$y = count($existingPortalLevels) > 0 ? $existingPortalLevels[0] : $world->getChunk(0, 0)?->getHighestBlockAt(0, 0);
		if($y === null || $y < $world->getMinY()){
			$y = 60;
		}

		$this->endExitPortalStructure->generate($world, self::EXIT_PORTAL_CENTER_X, $y, self::EXIT_PORTAL_CENTER_Z);
	}

	private function placeChorusTrees(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		if(($chunkX * $chunkX + $chunkZ * $chunkZ) <= 4096){
			return;
		}

		if($this->getIslandHeight($chunkX, $chunkZ, 1, 1) <= 40.0){
			return;
		}

		$chunk = $world->getChunk($chunkX, $chunkZ);
		if($chunk === null){
			return;
		}

		$endStone = VanillaBlocks::END_STONE();
		for($i = 0, $count = $this->random->nextBoundedInt(5); $i < $count; ++$i){
			$x = ($chunkX << Chunk::COORD_BIT_SIZE) + $this->random->nextBoundedInt(Chunk::EDGE_LENGTH);
			$z = ($chunkZ << Chunk::COORD_BIT_SIZE) + $this->random->nextBoundedInt(Chunk::EDGE_LENGTH);
			$y = $chunk->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
			if($y === null || $y <= 0){
				continue;
			}
			if(
				$world->getBlockAt($x, $y + 1, $z)->getTypeId() === BlockTypeIds::AIR &&
				$world->getBlockAt($x, $y, $z)->hasSameTypeId($endStone)
			){
				$this->chorusTreeGenerator->generate($world, $this->random, new Vector3($x, $y + 1, $z));
			}
		}
	}

	private function placeEndGateways(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		if(($chunkX * $chunkX + $chunkZ * $chunkZ) <= 4096){
			return;
		}

		if($this->getIslandHeight($chunkX, $chunkZ, 1, 1) <= 40.0){
			return;
		}

		if($this->random->nextBoundedInt(700) !== 0){
			return;
		}

		$chunk = $world->getChunk($chunkX, $chunkZ);
		if($chunk === null){
			return;
		}

		$x = ($chunkX << Chunk::COORD_BIT_SIZE) + $this->random->nextBoundedInt(Chunk::EDGE_LENGTH);
		$z = ($chunkZ << Chunk::COORD_BIT_SIZE) + $this->random->nextBoundedInt(Chunk::EDGE_LENGTH);
		$highest = $chunk->getHighestBlockAt($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK);
		if($highest === null){
			return;
		}

		$y = $highest + $this->random->nextBoundedInt(7) + 3;
		if($y <= 1 || $y >= 254){
			return;
		}

		$this->endGatewayStructure->generate($world, $this->random, new Vector3($x, $y, $z));
	}

	private static function spawnEndCrystals(World $world, int $chunkX, int $chunkZ, int $seed) : void{
		$bedrock = VanillaBlocks::BEDROCK();
		foreach(self::computeObsidianPillars($seed) as $pillar){
			$x = $pillar["centerX"];
			$z = $pillar["centerZ"];
			if(($x >> Chunk::COORD_BIT_SIZE) !== $chunkX || ($z >> Chunk::COORD_BIT_SIZE) !== $chunkZ){
				continue;
			}

			$height = $pillar["height"];
			if(!$world->getBlockAt($x, $height, $z)->hasSameTypeId($bedrock)){
				continue;
			}

			$crystalY = $height + 1;
			$bb = new AxisAlignedBB($x, $crystalY, $z, $x + 1, $crystalY + 2, $z + 1);
			foreach($world->getNearbyEntities($bb) as $entity){
				if($entity instanceof EndCrystal){
					continue 2;
				}
			}

			$crystal = new EndCrystal(new Location($x + 0.5, $crystalY, $z + 0.5, $world, 0, 0), CompoundTag::create());
			$crystal->setShowBase(true);
			$crystal->spawnToAll();
		}
	}

	private static function spawnEndGatewayTiles(World $world, int $chunkX, int $chunkZ) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ);
		if($chunk === null){
			return;
		}

		$gatewayType = VanillaBlocks::END_GATEWAY()->getTypeId();
		$spawn = $world->getSpawnLocation();
		$exitPortal = new Vector3($spawn->getFloorX(), $spawn->getFloorY(), $spawn->getFloorZ());

		$baseX = $chunkX << Chunk::COORD_BIT_SIZE;
		$baseZ = $chunkZ << Chunk::COORD_BIT_SIZE;
		for($lx = 0; $lx < Chunk::EDGE_LENGTH; ++$lx){
			for($lz = 0; $lz < Chunk::EDGE_LENGTH; ++$lz){
				$highest = $chunk->getHighestBlockAt($lx, $lz);
				if($highest === null){
					continue;
				}
				$wx = $baseX + $lx;
				$wz = $baseZ + $lz;
				$maxY = min($world->getMaxY() - 1, $highest + 10);
				for($y = max($world->getMinY(), $highest); $y <= $maxY; ++$y){
					if($world->getBlockAt($wx, $y, $wz)->getTypeId() !== $gatewayType){
						continue;
					}
					$pos = new Vector3($wx, $y, $wz);
					if($world->getTileAt($wx, $y, $wz) !== null){
						continue;
					}
					$tile = new TileEndGateway($world, $pos);
					$tile->setExitPortal($exitPortal);
					$world->addTile($tile);
				}
			}
		}
	}

	private function placeEndPlatform(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		if((100 >> Chunk::COORD_BIT_SIZE) !== $chunkX || 0 !== $chunkZ){
			return;
		}
		$obsidian = VanillaBlocks::OBSIDIAN();
		$air = VanillaBlocks::AIR();
		for($i = -2; $i <= 2; ++$i){
			for($j = -2; $j <= 2; ++$j){
				$x = 100 + $j;
				$z = $i;
				$world->setBlockAt($x, 48, $z, $obsidian);
				$world->setBlockAt($x, 49, $z, $air);
				$world->setBlockAt($x, 50, $z, $air);
			}
		}
	}

	private function placeEndIslands(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		if(($chunkX * $chunkX + $chunkZ * $chunkZ) <= 4096){
			return;
		}

		if($this->random->nextBoundedInt(14) !== 0){
			return;
		}

		$height = $this->getIslandHeight($chunkX, $chunkZ, 1, 1);
		if($height >= -20.0){
			return;
		}

		$cx = $chunkX << Chunk::COORD_BIT_SIZE;
		$cz = $chunkZ << Chunk::COORD_BIT_SIZE;
		$endStone = VanillaBlocks::END_STONE();

		$this->generateEndIslandObject($world, $cx + 8 + $this->random->nextBoundedInt(16), 55 + $this->random->nextBoundedInt(16), $cz + 8 + $this->random->nextBoundedInt(16), $endStone);
		if($this->random->nextBoundedInt(4) === 0){
			$this->generateEndIslandObject($world, $cx + 8 + $this->random->nextBoundedInt(16), 55 + $this->random->nextBoundedInt(16), $cz + 8 + $this->random->nextBoundedInt(16), $endStone);
		}
	}

	private function generateEndIslandObject(ChunkManager $world, int $baseX, int $baseY, int $baseZ, Block $endStone) : void{
		$n = (float) ($this->random->nextBoundedInt(3) + 4);
		for($y = 0; $n > 0.5; --$y){
			for($x = (int) floor(-$n); $x <= (int) ceil($n); ++$x){
				for($z = (int) floor(-$n); $z <= (int) ceil($n); ++$z){
					if((float) ($x * $x + $z * $z) <= ($n + 1.0) * ($n + 1.0)){
						$world->setBlockAt($baseX + $x, $baseY + $y, $baseZ + $z, $endStone);
					}
				}
			}
			$n -= (float) ($this->random->nextBoundedInt(2) + 0.5);
		}
	}

	private function placeObsidianPillars(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		foreach($this->obsidianPillars as $pillar){
			if(($pillar["centerX"] >> Chunk::COORD_BIT_SIZE) !== $chunkX || ($pillar["centerZ"] >> Chunk::COORD_BIT_SIZE) !== $chunkZ){
				continue;
			}
			self::generateObsidianPillar($world, $pillar);
		}
	}

	public static function ensureObsidianPillars(World $world) : void{
		foreach(self::computeObsidianPillars($world->getSeed()) as $pillar){
			$chunkX = $pillar["centerX"] >> Chunk::COORD_BIT_SIZE;
			$chunkZ = $pillar["centerZ"] >> Chunk::COORD_BIT_SIZE;
			if(!$world->isChunkGenerated($chunkX, $chunkZ)){
				$world->orderChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
					static function() use ($world, $pillar) : void{
						self::generateObsidianPillar($world, $pillar);
					},
					static function() : void{}
				);
				continue;
			}
			$world->loadChunk($chunkX, $chunkZ);
			self::generateObsidianPillar($world, $pillar);
		}
	}

	private static function setBlockIfChunkReady(ChunkManager $world, int $x, int $y, int $z, Block $block) : void{
		if(!$world->isInWorld($x, $y, $z)){
			return;
		}
		if($world instanceof World){
			$chunkX = $x >> Chunk::COORD_BIT_SIZE;
			$chunkZ = $z >> Chunk::COORD_BIT_SIZE;
			if(!$world->isChunkGenerated($chunkX, $chunkZ)){
				return;
			}
		}
		$world->setBlockAt($x, $y, $z, $block);
	}

	private static function getBlockIfChunkReady(ChunkManager $world, int $x, int $y, int $z) : ?Block{
		if(!$world->isInWorld($x, $y, $z)){
			return null;
		}
		if($world instanceof World){
			$chunkX = $x >> Chunk::COORD_BIT_SIZE;
			$chunkZ = $z >> Chunk::COORD_BIT_SIZE;
			if(!$world->isChunkGenerated($chunkX, $chunkZ)){
				return null;
			}
		}
		return $world->getBlockAt($x, $y, $z);
	}

	private static function generateObsidianPillar(ChunkManager $world, array $pillar) : void{
		$obsidian = VanillaBlocks::OBSIDIAN();
		$bedrock = VanillaBlocks::BEDROCK();
		$fire = VanillaBlocks::FIRE();
		$ironBars = VanillaBlocks::IRON_BARS();

		$x = $pillar["centerX"];
		$z = $pillar["centerZ"];
		$topY = $pillar["height"];
		$radius = $pillar["radius"];
		$guarded = $pillar["guarded"];

		for($y = 0; $y < $topY; ++$y){
			for($j = -$radius; $j <= $radius; ++$j){
				for($k = -$radius; $k <= $radius; ++$k){
					if($j * $j + $k * $k <= $radius * $radius + 1){
						self::setBlockIfChunkReady($world, $x + $j, $y, $z + $k, $obsidian);
					}
				}
			}
		}

		if($guarded){
			for($i = -2; $i <= 2; ++$i){
				for($j = -2; $j <= 2; ++$j){
					if(abs($i) === 2 || abs($j) === 2){
						for($k = 0; $k < 3; ++$k){
							self::setBlockIfChunkReady($world, $x + $i, $topY + $k, $z + $j, $ironBars);
						}
					}
					self::setBlockIfChunkReady($world, $x + $i, $topY + 3, $z + $j, $ironBars);
				}
			}
		}

		self::setBlockIfChunkReady($world, $x, $topY, $z, $bedrock);
		self::setBlockIfChunkReady($world, $x, $topY + 1, $z, $fire);
	}

	private static function findEndStoneSurfaceY(ChunkManager $world, int $x, int $z) : ?int{
		$endStone = VanillaBlocks::END_STONE()->getTypeId();
		for($y = min(90, $world->getMaxY() - 1); $y >= $world->getMinY(); --$y){
			$block = self::getBlockIfChunkReady($world, $x, $y, $z);
			if($block === null){
				continue;
			}
			if($block->getTypeId() === $endStone){
				return $y;
			}
		}
		return null;
	}

	public function getIslandHeight(int $chunkX, int $chunkZ, int $x, int $z) : float{
		$x1 = (float) ($chunkX * 2 + $x);
		$z1 = (float) ($chunkZ * 2 + $z);
		$islandHeight1 = self::clampFloat(100.0 - sqrt(($x1 * $x1) + ($z1 * $z1)) * 8.0, -100.0, 80.0);

		for($i = -12; $i <= 12; ++$i){
			for($j = -12; $j <= 12; ++$j){
				$x2 = $chunkX + $i;
				$z2 = $chunkZ + $j;
				if(($x2 * $x2 + $z2 * $z2) > 4096
					&& $this->islandNoise->getValue((float) $x2, (float) $z2) < -0.9){
					$x1 = (float) ($x - $i * 2);
					$z1 = (float) ($z - $j * 2);
					$islandHeight2 = 100.0 - sqrt(($x1 * $x1) + ($z1 * $z1))
						* (fmod(abs((float) $x2) * 3439.0 + abs((float) $z2) * 147.0, 13.0) + 9.0);
					$islandHeight2 = self::clampFloat($islandHeight2, -100.0, 80.0);
					$islandHeight1 = max($islandHeight1, $islandHeight2);
				}
			}
		}

		return $islandHeight1;
	}

	private static function clampFloat(float $v, float $min, float $max) : float{
		return max($min, min($max, $v));
	}
}
