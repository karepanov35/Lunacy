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
namespace pocketmine\block\utils;

/**
 * Represents copper blocks that have oxidized and waxed variations.
 */
interface CopperMaterial{

	public function getOxidation() : CopperOxidation;

	/**
	 * @return $this
	 */
	public function setOxidation(CopperOxidation $oxidation) : CopperMaterial;

	public function isWaxed() : bool;

	/**
	 * @return $this
	 */
	public function setWaxed(bool $waxed) : CopperMaterial;
}
