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
namespace pocketmine\world\light;

final class LightPropagationContext{

	/** @phpstan-var \SplQueue<array{int, int, int}> */
	public \SplQueue $spreadQueue;
	/**
	 * @var int[]|true[]
	 * @phpstan-var array<int, int|true>
	 */
	public array $spreadVisited = [];

	/** @phpstan-var \SplQueue<array{int, int, int, int}> */
	public \SplQueue $removalQueue;
	/**
	 * @var true[]
	 * @phpstan-var array<int, true>
	 */
	public array $removalVisited = [];

	public function __construct(){
		$this->removalQueue = new \SplQueue();
		$this->spreadQueue = new \SplQueue();
	}
}
