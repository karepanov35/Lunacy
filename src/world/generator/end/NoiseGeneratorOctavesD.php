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

use pocketmine\utils\Random;
use pocketmine\world\generator\noise\glowstone\PerlinNoise;

final class NoiseGeneratorOctavesD{

	/** @var PerlinNoise[] */
	private array $generatorCollection;
	private int $octaves;

	public function __construct(Random $seed, int $octavesIn){
		$this->octaves = $octavesIn;
		for($i = 0; $i < $octavesIn; ++$i){
			$this->generatorCollection[$i] = new PerlinNoise($seed);
		}
	}

	/**
	 * @param float[]|null $noiseArray
	 * @return float[]
	 */
	public function generateNoiseOctaves(?array $noiseArray, int $xOffset, int $yOffset, int $zOffset, int $xSize, int $ySize, int $zSize, float $xScale, float $yScale, float $zScale) : array{
		$len = $xSize * $ySize * $zSize;
		$noiseArray = array_fill(0, $len, 0.0);

		$d3 = 1.0;
		for($j = 0; $j < $this->octaves; ++$j){
			$d0 = $xOffset * $d3 * $xScale;
			$d1 = $yOffset * $d3 * $yScale;
			$d2 = $zOffset * $d3 * $zScale;
			$k = (int) floor($d0);
			$l = (int) floor($d2);
			$d0 -= $k;
			$d2 -= $l;
			$k %= 16777216;
			$l %= 16777216;
			if($k < 0){
				$k += 16777216;
			}
			if($l < 0){
				$l += 16777216;
			}
			$d0 += $k;
			$d2 += $l;

			$this->generatorCollection[$j]->getNoise($noiseArray, $d0, $d1, $d2, $xSize, $ySize, $zSize, $xScale * $d3, $yScale * $d3, $zScale * $d3, $d3);
			$d3 /= 2.0;
		}

		return $noiseArray;
	}
}
