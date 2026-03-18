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

final class CrashDumpDataGeneral{

	/**
	 * @param string[] $composer_libraries
	 * @phpstan-param array<string, string> $composer_libraries
	 */
	public function __construct(
		public string $name,
		public string $base_version,
		public int $build,
		public bool $is_dev,
		public int $protocol,
		public string $git,
		public string $uname,
		public string $php,
		public string $zend,
		public string $php_os,
		public string $os,
		public array $composer_libraries,
	){}
}
