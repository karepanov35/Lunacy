<?php

declare(strict_types=1);

namespace pocketmine\world\generator\end;

use pocketmine\utils\Random;
use function cos;
use function intdiv;
use function sin;

final class EndObsidianPillar{

	public function __construct(
		public readonly int $centerX,
		public readonly int $centerZ,
		public readonly int $radius,
		public readonly int $height,
		public readonly bool $guarded
	){}

	/**
	 * @return EndObsidianPillar[]
	 */
	public static function computeAll(int $worldSeed) : array{
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
			$result[] = new self(
				(int) (42 * cos(2 * (-M_PI + (M_PI / 10) * $i))),
				(int) (42 * sin(2 * (-M_PI + (M_PI / 10) * $i))),
				2 + intdiv($pillar, 3),
				76 + $pillar * 3,
				$pillar === 1 || $pillar === 2
			);
		}

		return $result;
	}
}
