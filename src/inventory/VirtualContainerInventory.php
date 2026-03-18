<?php


/*
 *
 *
 *в–’в–€в–‘в–‘в–‘ в–’в–€в–‘в–’в–€ в–’в–€в–„в–‘в–’в–€ в–‘в–€в–Ђв–Ђв–€ в–’в–€в–Ђв–Ђв–€ в–’в–€в–‘в–‘в–’в–€ 
 *в–’в–€в–‘в–‘в–‘ в–’в–€в–‘в–’в–€ в–’в–€в–’в–€в–’в–€ в–’в–€в–„в–„в–€ в–’в–€в–‘в–‘в–‘ в–’в–€в–„в–„в–„в–€ 
 *в–’в–€в–„в–„в–€ в–‘в–Ђв–„в–„в–Ђ в–’в–€в–‘в–‘в–Ђв–€ в–’в–€в–‘в–’в–€ в–’в–€в–„в–„в–€ в–‘в–‘в–’в–€в–‘в–‘
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
namespace pocketmine\inventory;

/**
 * Inventory that opens as a container UI without a block in the world.
 * The client is sent ContainerOpenPacket::entityInv() so no block position is required.
 */
interface VirtualContainerInventory extends Inventory{

	/**
	 * Entity ID to send in the container open packet (usually the viewer's entity ID).
	 */
	public function getEntityId() : int;
}
