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
namespace pocketmine\utils;

trait SingletonTrait{
	/** @var self|null */
	private static $instance = null;

	private static function make() : self{
		return new self();
	}

	public static function getInstance() : self{
		if(self::$instance === null){
			self::$instance = self::make();
		}
		return self::$instance;
	}

	public static function setInstance(self $instance) : void{
		self::$instance = $instance;
	}

	public static function reset() : void{
		self::$instance = null;
	}
}
