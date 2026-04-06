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
namespace pocketmine\item;

use pocketmine\entity\Entity;
use pocketmine\entity\Wolf;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class Lead extends Item{

	public function onInteractEntity(Player $player, Entity $entity, Vector3 $clickVector) : bool{
		if(!($entity instanceof Wolf)){
			return false;
		}

		$r = $entity->toggleLeashWithLead($player);
		if($r === Wolf::LEASH_INTERACT_NONE){
			return false;
		}
		if($r === Wolf::LEASH_INTERACT_ATTACHED){
			$this->pop();
		}elseif($r === Wolf::LEASH_INTERACT_DETACHED){
			foreach($player->getInventory()->addItem(VanillaItems::LEAD()) as $leftover){
				$player->dropItem($leftover);
			}
		}

		return true;
	}
}
