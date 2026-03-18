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
namespace pocketmine\event\player;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\lang\Translatable;
use pocketmine\player\Player;

/**
 * Called when a player is kicked (forcibly disconnected) from the server, e.g. if an operator used /kick.
 */
class PlayerKickEvent extends PlayerEvent implements Cancellable{
	use CancellableTrait;
	use PlayerDisconnectEventTrait;

	public function __construct(
		Player $player,
		protected Translatable|string $disconnectReason,
		protected Translatable|string $quitMessage,
		protected Translatable|string|null $disconnectScreenMessage
	){
		$this->player = $player;
	}

	/**
	 * Sets the quit message broadcasted to other players.
	 */
	public function setQuitMessage(Translatable|string $quitMessage) : void{
		$this->quitMessage = $quitMessage;
	}

	/**
	 * Returns the quit message broadcasted to other players, e.g. "Steve left the game".
	 */
	public function getQuitMessage() : Translatable|string{
		return $this->quitMessage;
	}
}
