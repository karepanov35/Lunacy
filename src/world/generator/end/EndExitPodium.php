<?php

declare(strict_types=1);

namespace pocketmine\world\generator\end;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\world\ChunkManager;
use function abs;

final class EndExitPodium{

	private const PODIUM_RADIUS = 4;
	private const PODIUM_PILLAR_HEIGHT = 4;
	private const RIM_RADIUS = 1;
	private const CORNER_ROUNDING = 8.0;

	public static function place(ChunkManager $world) : void{
		$portalY = self::findPortalY($world, 0, 0);

		$endStone = VanillaBlocks::END_STONE();
		$bedrock = VanillaBlocks::BEDROCK();
		$air = VanillaBlocks::AIR();
		$torch = VanillaBlocks::TORCH();

		$outerRadius = self::PODIUM_RADIUS + self::RIM_RADIUS + 0.5;
		$innerRadius = self::PODIUM_RADIUS - 0.5;
		$innerCorner = self::CORNER_ROUNDING - 5.0;

		for($dx = -(self::PODIUM_RADIUS + self::RIM_RADIUS); $dx <= self::PODIUM_RADIUS + self::RIM_RADIUS; ++$dx){
			for($dz = -(self::PODIUM_RADIUS + self::RIM_RADIUS); $dz <= self::PODIUM_RADIUS + self::RIM_RADIUS; ++$dz){
				if(!self::isInsideRoundedSquare($dx, $dz, $outerRadius, self::CORNER_ROUNDING)){
					continue;
				}
				if(self::isInsideRoundedSquare($dx, $dz, $innerRadius, $innerCorner)){
					continue;
				}
				$world->setBlockAt($dx, $portalY - 1, $dz, $endStone);
			}
		}

		for($h = 0; $h < self::PODIUM_PILLAR_HEIGHT; ++$h){
			$world->setBlockAt(0, $portalY + $h, 0, $bedrock);
		}

		$rimY = $portalY + self::PODIUM_PILLAR_HEIGHT - 1;
		for($dx = -(self::PODIUM_RADIUS + self::RIM_RADIUS); $dx <= self::PODIUM_RADIUS + self::RIM_RADIUS; ++$dx){
			for($dz = -(self::PODIUM_RADIUS + self::RIM_RADIUS); $dz <= self::PODIUM_RADIUS + self::RIM_RADIUS; ++$dz){
				if(!self::isInsideRoundedSquare($dx, $dz, $outerRadius, self::CORNER_ROUNDING)){
					continue;
				}
				if(self::isInsideRoundedSquare($dx, $dz, $innerRadius, $innerCorner)){
					continue;
				}
				$world->setBlockAt($dx, $rimY, $dz, $bedrock);
			}
		}

		$torchY = $rimY;
		$world->setBlockAt(0, $torchY, -1, clone $torch->setFacing(Facing::SOUTH));
		$world->setBlockAt(0, $torchY, 1, clone $torch->setFacing(Facing::NORTH));
		$world->setBlockAt(-1, $torchY, 0, clone $torch->setFacing(Facing::EAST));
		$world->setBlockAt(1, $torchY, 0, clone $torch->setFacing(Facing::WEST));

		for($dx = -self::PODIUM_RADIUS; $dx <= self::PODIUM_RADIUS; ++$dx){
			for($dz = -self::PODIUM_RADIUS; $dz <= self::PODIUM_RADIUS; ++$dz){
				if($dx === 0 && $dz === 0){
					continue;
				}
				if(!self::isInsideRoundedSquare($dx, $dz, $innerRadius, $innerCorner)){
					continue;
				}
				$world->setBlockAt($dx, $portalY, $dz, $air);
			}
		}
	}

	private static function findPortalY(ChunkManager $world, int $x, int $z) : int{
		for($y = $world->getMaxY() - 1; $y >= $world->getMinY(); --$y){
			if($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::END_STONE){
				return $y + 1;
			}
		}

		return max($world->getMinY(), 0);
	}

	private static function isInsideRoundedSquare(int $x, int $z, float $radius, float $cornerRadius) : bool{
		$ax = abs($x);
		$az = abs($z);
		$f = $radius - $cornerRadius;
		if($f < 0.0){
			return ($ax * $ax + $az * $az) <= ($radius * $radius);
		}
		if($ax <= $f || $az <= $f){
			return true;
		}
		$d0 = $ax - $f;
		$d1 = $az - $f;
		return ($d0 * $d0 + $d1 * $d1) <= ($cornerRadius * $cornerRadius);
	}
}
