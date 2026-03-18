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

/**
 * This trait provides destructor callback functionality to objects which use it. This enables a weakmap-like system
 * to function without actually having weak maps.
 * TODO: remove this in PHP 8
 */
trait DestructorCallbackTrait{
	/**
	 * @var ObjectSet
	 * @phpstan-var ObjectSet<\Closure() : void>|null
	 */
	private $destructorCallbacks = null;

	/** @phpstan-return ObjectSet<\Closure() : void> */
	public function getDestructorCallbacks() : ObjectSet{
		return $this->destructorCallbacks === null ? ($this->destructorCallbacks = new ObjectSet()) : $this->destructorCallbacks;
	}

	public function __destruct(){
		if($this->destructorCallbacks !== null){
			foreach($this->destructorCallbacks as $callback){
				$callback();
			}
		}
	}
}
