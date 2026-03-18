<?php

declare(strict_types=1);

namespace pocketmine\world\sound;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;

class EndermanStareSound implements Sound{

	public function encode(Vector3 $pos) : array{
		return [PlaySoundPacket::create("mob.endermen.stare", $pos->getX(), $pos->getY(), $pos->getZ(), 1.0, 1.0)];
	}
}
