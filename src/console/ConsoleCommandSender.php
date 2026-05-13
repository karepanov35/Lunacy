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
namespace pocketmine\console;

use pocketmine\command\CommandSender;
use pocketmine\lang\Language;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\PermissibleBase;
use pocketmine\permission\PermissibleDelegateTrait;
use pocketmine\Server;
use pocketmine\utils\Terminal;
use pocketmine\utils\TextFormat;
use function explode;
use function trim;
use const PHP_INT_MAX;

class ConsoleCommandSender implements CommandSender{
	use PermissibleDelegateTrait;

	/** @phpstan-var positive-int|null */
	protected ?int $lineHeight = null;

	public function __construct(
		private Server $server,
		private Language $language
	){
		$this->perm = new PermissibleBase([DefaultPermissions::ROOT_CONSOLE => true]);
	}

	public function getServer() : Server{
		return $this->server;
	}

	public function getLanguage() : Language{
		return $this->language;
	}

	public function sendMessage(Translatable|string $message) : void{
		if($message instanceof Translatable){
			$message = $this->getLanguage()->translate($message);
		}

		foreach(explode("\n", trim($message), limit: PHP_INT_MAX) as $line){
			Terminal::writeLine(self::gradientPrefix() . TextFormat::addBase(TextFormat::WHITE, $line));
		}
	}

	/**
	 * Returns "Command output | " with a blue→purple gradient using ANSI true-colour codes.
	 * Matches the gradient from the [Lunacy/INFO] prefix style.
	 */
	private static function gradientPrefix() : string{
		// Start colour #159EF0 → end colour #AC90FE, 17 characters in "Command output | "
		$text   = "Command output | ";
		$startR = 0x15; $startG = 0x9E; $startB = 0xF0;
		$endR   = 0xAC; $endG   = 0x90; $endB   = 0xFE;
		$len    = strlen($text);
		$result = "";
		for($i = 0; $i < $len; ++$i){
			$t = $len > 1 ? $i / ($len - 1) : 0;
			$r = (int) round($startR + ($endR - $startR) * $t);
			$g = (int) round($startG + ($endG - $startG) * $t);
			$b = (int) round($startB + ($endB - $startB) * $t);
			$result .= "\x1b[38;2;{$r};{$g};{$b}m" . $text[$i];
		}
		$result .= "\x1b[0m";
		return $result;
	}

	public function getName() : string{
		return "CONSOLE";
	}

	public function getScreenLineHeight() : int{
		return $this->lineHeight ?? PHP_INT_MAX;
	}

	public function setScreenLineHeight(?int $height) : void{
		if($height !== null && $height < 1){
			throw new \InvalidArgumentException("Line height must be at least 1");
		}
		$this->lineHeight = $height;
	}
}
