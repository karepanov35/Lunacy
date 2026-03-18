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
namespace pocketmine\network\mcpe\auth;

use pocketmine\lang\Translatable;

class VerifyLoginException extends \RuntimeException{

	private Translatable|string $disconnectMessage;

	public function __construct(string $message, Translatable|string|null $disconnectMessage = null, int $code = 0, ?\Throwable $previous = null){
		parent::__construct($message, $code, $previous);
		$this->disconnectMessage = $disconnectMessage ?? $message;
	}

	public function getDisconnectMessage() : Translatable|string{ return $this->disconnectMessage; }
}
