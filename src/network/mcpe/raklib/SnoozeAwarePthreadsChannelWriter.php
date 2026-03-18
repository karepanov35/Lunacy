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
namespace pocketmine\network\mcpe\raklib;

use pmmp\thread\ThreadSafeArray;
use pocketmine\snooze\SleeperNotifier;
use raklib\server\ipc\InterThreadChannelWriter;

final class SnoozeAwarePthreadsChannelWriter implements InterThreadChannelWriter{
	/**
	 * @phpstan-param ThreadSafeArray<int, string> $buffer
	 */
	public function __construct(
		private ThreadSafeArray $buffer,
		private SleeperNotifier $notifier
	){}

	public function write(string $str) : void{
		$this->buffer[] = $str;
		$this->notifier->wakeupSleeper();
	}
}
