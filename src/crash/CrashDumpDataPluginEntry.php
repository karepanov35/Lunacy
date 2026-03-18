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
namespace pocketmine\crash;

final class CrashDumpDataPluginEntry{
	/**
	 * @param string[] $authors
	 * @param string[] $api
	 * @param string[] $depends
	 * @param string[] $softDepends
	 */
	public function __construct(
		public string $name,
		public string $version,
		public array $authors,
		public array $api,
		public bool $enabled,
		public array $depends,
		public array $softDepends,
		public string $main,
		public string $load,
		public string $website,
	){}
}
