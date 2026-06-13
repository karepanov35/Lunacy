<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\inventory;

use pocketmine\entity\ChestMinecart;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\player\Player;
use pocketmine\world\sound\ChestCloseSound;
use pocketmine\world\sound\ChestOpenSound;
use function count;

/**
 * Chest minecart container inventory (27 slots).
 */
class MinecartChestInventory extends MinecartInventory implements MinecartChestInventoryInterface{

	public function __construct(ChestMinecart $entity){
		parent::__construct($entity, 27);
	}

	public function getChestMinecart() : ChestMinecart{
		$holder = $this->getHolder();
		if(!$holder instanceof ChestMinecart){
			throw new \LogicException("Expected ChestMinecart inventory holder");
		}
		return $holder;
	}

	public function onOpen(Player $who) : void{
		parent::onOpen($who);

		if(count($this->getViewers()) === 1){
			$this->animateChest(true);
			$minecart = $this->getChestMinecart();
			$minecart->getWorld()->addSound($minecart->getLocation(), new ChestOpenSound());
		}
	}

	public function onClose(Player $who) : void{
		if(count($this->getViewers()) === 1){
			$this->animateChest(false);
			$minecart = $this->getChestMinecart();
			$minecart->getWorld()->addSound($minecart->getLocation(), new ChestCloseSound());
		}
		parent::onClose($who);
	}

	private function animateChest(bool $isOpen) : void{
		$minecart = $this->getChestMinecart();
		$minecart->getWorld()->broadcastPacketToViewers(
			$minecart->getLocation(),
			BlockEventPacket::create(
				BlockPosition::fromVector3($minecart->getLocation()),
				1,
				$isOpen ? 1 : 0
			)
		);
	}
}
