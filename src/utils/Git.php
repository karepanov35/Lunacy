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

use function file_get_contents;
use function is_file;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

final class Git{

	private function __construct(){
		//NOOP
	}

	/**
	 * Returns the git hash of the currently checked out head of the given repository, or null on failure.
	 *
	 * @param bool $dirty reference parameter, set to whether the repo has local changes
	 */
	public static function getRepositoryState(string $dir, bool &$dirty) : ?string{
		if(Process::execute("git -C \"$dir\" rev-parse HEAD", $out) === 0 && strlen($out = trim($out)) === 40){
			if(Process::execute("git -C \"$dir\" diff --quiet") === 1 || Process::execute("git -C \"$dir\" diff --cached --quiet") === 1){ //Locally-modified
				$dirty = true;
			}
			return $out;
		}
		return null;
	}

	/**
	 * Infallible, returns a string representing git state, or a string of zeros on failure.
	 * If the repo is dirty, a "-dirty" suffix is added.
	 */
	public static function getRepositoryStatePretty(string $dir) : string{
		$dirty = false;
		$detectedHash = self::getRepositoryState($dir, $dirty);
		if($detectedHash !== null){
			return $detectedHash . ($dirty ? "-dirty" : "");
		}

		$fallback = self::readHeadHash($dir);
		if($fallback !== null){
			return $fallback . ($dirty ? "-dirty" : "");
		}

		return str_repeat("00", 20);
	}

	/**
	 * @param bool $dirty reference parameter, set to whether the repo has local changes
	 */
	public static function getShortRepositoryState(string $dir, bool &$dirty) : ?string{
		if(Process::execute("git -C \"$dir\" rev-parse --short HEAD", $out) === 0){
			$short = trim($out);
			if($short !== ""){
				if(Process::execute("git -C \"$dir\" diff --quiet") === 1 || Process::execute("git -C \"$dir\" diff --cached --quiet") === 1){
					$dirty = true;
				}
				return $short;
			}
		}

		$full = self::getRepositoryState($dir, $dirty);
		if($full !== null){
			return substr($full, 0, 7);
		}

		$fallback = self::readHeadHash($dir);
		if($fallback !== null){
			return substr($fallback, 0, 7);
		}

		return null;
	}

	public static function getShortRepositoryStatePretty(string $dir) : ?string{
		$dirty = false;
		$short = self::getShortRepositoryState($dir, $dirty);
		if($short === null){
			return null;
		}
		return $short . ($dirty ? "-dirty" : "");
	}

	private static function readHeadHash(string $dir) : ?string{
		$headFile = $dir . DIRECTORY_SEPARATOR . ".git" . DIRECTORY_SEPARATOR . "HEAD";
		if(!is_file($headFile)){
			return null;
		}

		$head = trim((string) file_get_contents($headFile));
		if($head === ""){
			return null;
		}

		if(str_starts_with($head, "ref: ")){
			$ref = trim(substr($head, 5));
			$refFile = $dir . DIRECTORY_SEPARATOR . ".git" . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $ref);
			if(!is_file($refFile)){
				return null;
			}
			$hash = trim((string) file_get_contents($refFile));
			return strlen($hash) === 40 ? $hash : null;
		}

		return strlen($head) === 40 ? $head : null;
	}
}
