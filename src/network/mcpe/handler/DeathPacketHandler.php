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
namespace pocketmine\network\mcpe\handler;

use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\DeathInfoPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\RespawnPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\player\Player;

class DeathPacketHandler extends PacketHandler{
	public function __construct(
		private Player $player,
		private NetworkSession $session,
		private InventoryManager $inventoryManager,
		private Translatable|string $deathMessage
	){}

	public function setUp() : void{
		$this->session->sendDataPacket(RespawnPacket::create(
			$this->player->getOffsetPosition($this->player->getSpawn()),
			RespawnPacket::SEARCHING_FOR_SPAWN,
			$this->player->getId()
		));

		/** @var string[] $parameters */
		$parameters = [];
		if($this->deathMessage instanceof Translatable){
			$language = $this->player->getLanguage();
			if(!$this->player->getServer()->isLanguageForced()){
				[$message, $parameters] = $this->session->prepareClientTranslatableMessage($this->deathMessage);
			}else{
				$message = $language->translate($this->deathMessage);
			}
		}else{
			$message = $this->deathMessage;
		}
		$this->session->sendDataPacket(DeathInfoPacket::create($message, $parameters));
	}

	public function handlePlayerAction(PlayerActionPacket $packet) : bool{
		if($packet->action === PlayerAction::RESPAWN){
			$this->player->respawn();
			return true;
		}

		return false;
	}

	public function handleContainerClose(ContainerClosePacket $packet) : bool{
		$this->inventoryManager->onClientRemoveWindow($packet->windowId);
		return true;
	}

	public function handleRespawn(RespawnPacket $packet) : bool{
		if($packet->respawnState === RespawnPacket::CLIENT_READY_TO_SPAWN){
			$this->session->sendDataPacket(RespawnPacket::create(
				$this->player->getOffsetPosition($this->player->getSpawn()),
				RespawnPacket::READY_TO_SPAWN,
				$this->player->getId()
			));
			return true;
		}
		return false;
	}
}
