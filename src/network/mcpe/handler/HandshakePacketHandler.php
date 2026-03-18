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

use pocketmine\network\mcpe\protocol\ClientToServerHandshakePacket;

/**
 * Handler responsible for awaiting client response from crypto handshake.
 */
class HandshakePacketHandler extends PacketHandler{
	/**
	 * @phpstan-param \Closure() : void $onHandshakeCompleted
	 */
	public function __construct(private \Closure $onHandshakeCompleted){}

	public function handleClientToServerHandshake(ClientToServerHandshakePacket $packet) : bool{
		($this->onHandshakeCompleted)();
		return true;
	}
}
