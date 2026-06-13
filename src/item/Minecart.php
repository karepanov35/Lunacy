<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\block\BaseRail;
use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\entity\Minecart as MinecartEntity;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Minecart extends Item{

	public function getMaxStackSize() : int{ return 1; }

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if(!($blockClicked instanceof BaseRail)){
			return ItemUseResult::NONE;
		}

		$world   = $player->getWorld();
		$pos     = $blockClicked->getPosition();

		// На наклонных рельсах (shape 2-5) смещаемся вверх
		$yOffset = 0.0;
		if(method_exists($blockClicked, 'getShape')){
			$shape = $blockClicked->getShape();
			if($shape >= 2 && $shape <= 5) $yOffset = 0.5;
		}

		$loc = Location::fromObject(
			new Vector3($pos->x + 0.5, $pos->y + 0.0625 + $yOffset, $pos->z + 0.5),
			$world,
			0.0,
			0.0
		);

		$minecart = new MinecartEntity($loc, null);
		$minecart->spawnToAll();

		$this->pop();
		return ItemUseResult::SUCCESS;
	}
}
