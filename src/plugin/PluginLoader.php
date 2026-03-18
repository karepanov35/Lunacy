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

/**
 * Handles different types of plugins
 */
interface PluginLoader{

	/**
	 * Returns whether this PluginLoader can load the plugin in the given path.
	 */
	public function canLoadPlugin(string $path) : bool;

	/**
	 * Loads the plugin contained in $file
	 */
	public function loadPlugin(string $file) : void;

	/**
	 * Gets the PluginDescription from the file
	 * @throws PluginDescriptionParseException
	 */
	public function getPluginDescription(string $file) : ?PluginDescription;

	/**
	 * Returns the protocol prefix used to access files in this plugin, e.g. file://, phar://
	 */
	public function getAccessProtocol() : string;
}
