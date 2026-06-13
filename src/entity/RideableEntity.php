<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\player\Player;

/**
 * Интерфейс для верховых существ (лошадь, осёл, мул, верблюд и т.д.).
 * InGamePacketHandler и Player используют его для управления ездой.
 */
interface RideableEntity{

	public function getRider() : ?Player;

	public function mountPlayer(Player $player) : void;

	public function dismountPlayer() : void;

	public function applyRiderInput(float $moveVecX, float $moveVecZ, float $yaw) : void;

	public function isSaddled() : bool;

	/** Смещение по Y от origin сущности до «ног» всадника (Nukkit: getMountedOffset). */
	public function getMountedSeatHeight() : float;
}
