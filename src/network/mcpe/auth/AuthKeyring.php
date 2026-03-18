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

final class AuthKeyring{

	/**
	 * @param string[] $keys
	 * @phpstan-param array<string, string> $keys
	 */
	public function __construct(
		private string $issuer,
		private array $keys
	){}

	public function getIssuer() : string{ return $this->issuer; }

	/**
	 * Returns a (raw) DER public key associated with the given key ID
	 */
	public function getKey(string $keyId) : ?string{
		return $this->keys[$keyId] ?? null;
	}
}
