<?php

declare(strict_types=1);

namespace pocketmine\world\sound;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

final class EnderDragonDeathSound implements Sound{

	public function __construct(private Entity $entity){}

	public function encode(Vector3 $pos) : array{
		return [LevelSoundEventPacket::create(
			LevelSoundEvent::DEATH,
			$pos,
			-1,
			$this->entity::getNetworkTypeId(),
			false,
			false,
			$this->entity->getId(),
			null
		)];
	}
}
