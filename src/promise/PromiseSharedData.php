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
namespace pocketmine\promise;

/**
 * @internal
 * @see PromiseResolver
 * @phpstan-template TValue
 */
final class PromiseSharedData{
	/**
	 * @var \Closure[]
	 * @phpstan-var array<int, \Closure(TValue) : void>
	 */
	public array $onSuccess = [];

	/**
	 * @var \Closure[]
	 * @phpstan-var array<int, \Closure() : void>
	 */
	public array $onFailure = [];

	public ?bool $state = null;

	/** @phpstan-var TValue */
	public mixed $result;
}
