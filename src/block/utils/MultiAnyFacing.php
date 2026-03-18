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

use pocketmine\math\Facing;

interface MultiAnyFacing{

	/**
	 * @return int[]
	 * @see Facing
	 */
	public function getFaces() : array;

	public function hasFace(int $face) : bool;

	/**
	 * @return $this
	 *
	 * @see Facing
	 */
	public function setFace(int $face, bool $value) : self;

	/**
	 * @param int[] $faces
	 *
	 * @return $this
	 *
	 * @see Facing
	 */
	public function setFaces(array $faces) : self;

}
