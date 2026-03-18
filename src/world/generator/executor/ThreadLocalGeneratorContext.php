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
namespace pocketmine\world\generator\executor;

use pocketmine\world\generator\Generator;

/**
 * Manages thread-local caches for generators and the things needed to support them
 */
final class ThreadLocalGeneratorContext{
	/**
	 * @var self[]
	 * @phpstan-var array<int, self>
	 */
	private static array $contexts = [];

	public static function register(self $context, int $worldId) : void{
		self::$contexts[$worldId] = $context;
	}

	public static function unregister(int $worldId) : void{
		unset(self::$contexts[$worldId]);
	}

	public static function fetch(int $worldId) : ?self{
		return self::$contexts[$worldId] ?? null;
	}

	public function __construct(
		private Generator $generator,
		private int $worldMinY,
		private int $worldMaxY
	){}

	public function getGenerator() : Generator{ return $this->generator; }

	public function getWorldMinY() : int{ return $this->worldMinY; }

	public function getWorldMaxY() : int{ return $this->worldMaxY; }
}
