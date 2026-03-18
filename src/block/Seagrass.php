<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\SupportType;

class Seagrass extends Transparent{

	public function __construct(BlockIdentifier $idInfo, string $name, BlockTypeInfo $typeInfo){
		parent::__construct($idInfo, $name, $typeInfo);
	}

	public function isSolid() : bool{
		return false;
	}

	protected function recalculateCollisionBoxes() : array{
		return [];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}
}
