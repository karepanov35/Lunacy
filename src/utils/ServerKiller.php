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

use pocketmine\thread\Thread;
use function hrtime;
use function intdiv;

class ServerKiller extends Thread{
	private bool $stopped = false;

	public function __construct(
		public int $time = 15
	){}

	protected function onRun() : void{
		$start = hrtime(true);
		$remaining = $this->time * 1_000_000;
		$this->synchronized(function() use (&$remaining, $start) : void{
			while(!$this->stopped && $remaining > 0){
				$this->wait($remaining);
				$remaining -= intdiv(hrtime(true) - $start, 1000);
			}
		});
		if($remaining <= 0){
			echo "\nTook too long to stop, server was killed forcefully!\n";
			@Process::kill(Process::pid());
		}
	}

	public function quit() : void{
		$this->synchronized(function() : void{
			$this->stopped = true;
			$this->notify();
		});
		parent::quit();
	}

	public function getThreadName() : string{
		return "Server Killer";
	}
}
