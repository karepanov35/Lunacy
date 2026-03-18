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
namespace pocketmine\network;

interface RawPacketHandler{

	/**
	 * Returns a preg_match() compatible regex pattern used to filter packets on this handler. Only packets matching
	 * this pattern will be delivered to the handler.
	 */
	public function getPattern() : string;

	/**
	 * @throws PacketHandlingException
	 */
	public function handle(AdvancedNetworkInterface $interface, string $address, int $port, string $packet) : bool;
}
