<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\block\BaseRail;
use pocketmine\block\Block;
use pocketmine\entity\ChestMinecart;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class MinecartWithChest extends Item{

	public function getMaxStackSize() : int{ return 1; }

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if(!($blockClicked instanceof BaseRail)) return ItemUseResult::NONE;

		$pos     = $blockClicked->getPosition();
		$yOffset = 0.0;
		if(method_exists($blockClicked, 'getShape')){
			$s = $blockClicked->getShape();
			if($s >= 2 && $s <= 5) $yOffset = 0.5;
		}

		$loc = Location::fromObject(
			new Vector3($pos->x + 0.5, $pos->y + 0.0625 + $yOffset, $pos->z + 0.5),
			$player->getWorld(), 0.0, 0.0
		);
		(new ChestMinecart($loc, null))->spawnToAll();

		$this->pop();
		return ItemUseResult::SUCCESS;
	}
}
