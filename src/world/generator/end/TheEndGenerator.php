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
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\noise\Simplex;
use function abs;
use function cos;
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
	private Simplex $islandNoise;

	private int $localSeed1;
	private int $localSeed2;

	/** @var array<int, array{centerX: int, centerZ: int, radius: int, height: int, guarded: bool}> */
	private array $obsidianPillars;

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);
		$r = new Random($seed ^ 0x454E4453);
		$this->localSeed1 = $r->nextInt();
		$this->localSeed2 = $r->nextInt();

		$noiseSeed = new Random($seed ^ 0x4E4F4953);
		$this->roughnessNoiseOctaves = new NoiseGeneratorOctavesD($noiseSeed, 16);
		$this->roughnessNoiseOctaves2 = new NoiseGeneratorOctavesD(new Random($seed ^ 0x5231), 16);
		$this->detailNoiseOctaves = new NoiseGeneratorOctavesD(new Random($seed ^ 0x4431), 8);
		$this->islandNoise = new Simplex(new Random($seed ^ 0x53494D50), 4, 0.5, 1.0);

		$this->obsidianPillars = self::computeObsidianPillars($seed);
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
		$this->placeEndIslands($world, $chunkX, $chunkZ);
		$this->placeObsidianPillars($world, $chunkX, $chunkZ);
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
		$obsidian = VanillaBlocks::OBSIDIAN();
		$bedrock = VanillaBlocks::BEDROCK();
		$fire = VanillaBlocks::FIRE();
		$ironBars = VanillaBlocks::IRON_BARS();

		foreach($this->obsidianPillars as $pillar){
			$x = $pillar["centerX"];
			$z = $pillar["centerZ"];
			if(($x >> Chunk::COORD_BIT_SIZE) !== $chunkX || ($z >> Chunk::COORD_BIT_SIZE) !== $chunkZ){
				continue;
			}

			$height = $pillar["height"];
			$radius = $pillar["radius"];
			$guarded = $pillar["guarded"];

			for($i = 0; $i < $height; ++$i){
				for($j = -$radius; $j <= $radius; ++$j){
					for($k = -$radius; $k <= $radius; ++$k){
						if($j * $j + $k * $k <= $radius * $radius + 1){
							$world->setBlockAt($x + $j, $i, $z + $k, $obsidian);
						}
					}
				}
			}

			if($guarded){
				for($i = -2; $i <= 2; ++$i){
					for($j = -2; $j <= 2; ++$j){
						if(abs($i) === 2 || abs($j) === 2){
							for($k = 0; $k < 3; ++$k){
								$world->setBlockAt($x + $i, $height + $k, $z + $j, $ironBars);
							}
						}
						$world->setBlockAt($x + $i, $height + 3, $z + $j, $ironBars);
					}
				}
			}

			$world->setBlockAt($x, $height, $z, $bedrock);
			$world->setBlockAt($x, $height + 1, $z, $fire);
		}
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
					&& $this->islandNoise->noise2D((float) $x2, (float) $z2, true) < -0.9){
					$x1 = (float) ($x - $i * 2);
					$z1 = (float) ($z - $j * 2);
					$islandHeight2 = 100.0 - sqrt(($x1 * $x1) + ($z1 * $z1))
						* ((abs((float) $x2) * 3439.0 + abs((float) $z2) * 147.0) % 13.0 + 9.0);
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
