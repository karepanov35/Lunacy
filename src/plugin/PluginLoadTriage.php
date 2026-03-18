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
namespace pocketmine\plugin;

final class PluginLoadTriage{
	/**
	 * @var PluginLoadTriageEntry[]
	 * @phpstan-var array<string, PluginLoadTriageEntry>
	 */
	public array $plugins = [];
	/**
	 * @var string[][]
	 * @phpstan-var array<string, array<string>>
	 */
	public array $dependencies = [];
	/**
	 * @var string[][]
	 * @phpstan-var array<string, array<string>>
	 */
	public array $softDependencies = [];
}
