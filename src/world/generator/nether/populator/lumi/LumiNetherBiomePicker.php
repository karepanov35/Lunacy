<?php

declare(strict_types=1);

namespace pocketmine\world\generator\nether\populator\lumi;

use pocketmine\data\bedrock\BiomeIds;
use pocketmine\utils\Random;
use pocketmine\world\generator\noise\Simplex;

/**
 * Выбор биома Нижнего мира (логика Lumi) с адаптацией под PocketMine Simplex.
 *
 * Раньше сильное сглаживание «съедало» экстремумы — багровый/искажённый лес почти не появлялся.
 * Сейчас: без сглаживания по соседям (берём шум в точке), пороги смещены в сторону лесов.
 */
final class LumiNetherBiomePicker{

	/** Крупные регионы биомов */
	private const BIOME_AMPLIFICATION = 896.0;

	/**
	 * Порог «лесных» биомов (багровый / искажённый).
	 */
	private const THRESHOLD_FOREST = 0.02;

	/** Ниже — базальтовые дельты или долина песка душ; выше до леса — классический Ад */
	private const THRESHOLD_LOW = -0.28;

	private Simplex $primaryNoise;
	private Simplex $secondaryNoise;

	public function __construct(int $worldSeed){
		$r1 = new Random($worldSeed ^ 0x4E455448_4552); // "NETHR"
		$this->primaryNoise = new Simplex($r1, 4, 0.5, 1.0);
		$r2 = new Random($worldSeed ^ 0x42494F4D_4532); // "BIOM"
		$this->secondaryNoise = new Simplex($r2, 4, 0.5, 1.0);
	}

	/**
	 * Крупный шум + мелкая деталь (леса и низины читаются на карте биомов).
	 *
	 * @return float[]
	 * @phpstan-return array{0: float, 1: float}
	 */
	private function layeredNoise(int $blockX, int $blockZ) : array{
		$scale = self::BIOME_AMPLIFICATION;
		$scale2 = $scale * 2.0;
		$fine = $scale / 2.8;

		$largeP = $this->primaryNoise->noise2D($blockX / $scale, $blockZ / $scale, true);
		$fineP = $this->primaryNoise->noise2D($blockX / $fine + 19.2, $blockZ / $fine + 9.7, true);
		$value = $largeP * 0.62 + $fineP * 0.38;

		$largeS = $this->secondaryNoise->noise3D($blockX / $scale2, 0.0, $blockZ / $scale2, true);
		$fineS = $this->secondaryNoise->noise3D($blockX / ($scale2 / 2.1) + 11.0, 3.7, $blockZ / ($scale2 / 2.1) - 4.2, true);
		$secondaryValue = $largeS * 0.58 + $fineS * 0.42;

		return [$value, $secondaryValue];
	}

	public function pickBiome(int $blockX, int $blockZ) : int{
		[$value, $secondaryValue] = $this->layeredNoise($blockX, $blockZ);

		if($value >= self::THRESHOLD_FOREST){
			return $secondaryValue >= 0.0 ? BiomeIds::WARPED_FOREST : BiomeIds::CRIMSON_FOREST;
		}
		if($value >= self::THRESHOLD_LOW){
			return BiomeIds::HELL;
		}

		return $secondaryValue >= 0.0 ? BiomeIds::BASALT_DELTAS : BiomeIds::SOULSAND_VALLEY;
	}
}
