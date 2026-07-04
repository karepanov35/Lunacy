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
namespace pocketmine;

use pocketmine\utils\Git;
use pocketmine\utils\VersionString;
use function is_array;
use function is_int;
use function str_repeat;

final class VersionInfo{
	public const NAME = "Lunacy";
	public const BASE_VERSION = "0.1.6";
	public const API_VERSION = "5.0.0";
	public const IS_DEVELOPMENT_BUILD = false;
	public const BUILD_CHANNEL = "stable";
	public const GITHUB_URL = "https://github.com/karepanov35/Lunacy";
	public const GIT_UNKNOWN = "Dev Build";

	/**
	 * PocketMine-MP-specific version ID for world data. Used to determine what fixes need to be applied to old world
	 * data (e.g. stuff saved wrongly by past versions).
	 * This version supplements the Minecraft vanilla world version.
	 *
	 * This should be bumped if any **non-Mojang** BC-breaking change or bug fix is made to world save data of any kind
	 * (entities, tiles, blocks, biomes etc.). For example, if PM accidentally saved a block with its facing value
	 * swapped, we would bump this, but not if Mojang did the same change.
	 */
	public const WORLD_DATA_VERSION = 1;
	/**
	 * Name of the NBT tag used to store the world data version.
	 */
	public const TAG_WORLD_DATA_VERSION = "PMMPDataVersion"; //TAG_Long

	private function __construct(){
		//NOOP
	}

	private static ?string $gitHash = null;
	private static ?string $gitHashShort = null;

	public static function GIT_HASH() : string{
		if(self::$gitHash === null){
			self::$gitHash = self::resolveGitHash(false);
		}

		return self::$gitHash;
	}

	public static function GIT_HASH_SHORT() : string{
		if(self::$gitHashShort === null){
			self::$gitHashShort = self::resolveGitHash(true);
		}

		return self::$gitHashShort;
	}

	public static function isUnknownGitBuild() : bool{
		return self::GIT_HASH() === self::GIT_UNKNOWN;
	}

	private static function resolveGitHash(bool $short) : string{
		if(\Phar::running(true) === ""){
			if($short){
				$hash = Git::getShortRepositoryStatePretty(\pocketmine\PATH);
				if($hash !== null){
					return $hash;
				}
			}else{
				$hash = Git::getRepositoryStatePretty(\pocketmine\PATH);
				if($hash !== str_repeat("00", 20)){
					return $hash;
				}
			}

			$commitFile = \pocketmine\RESOURCE_PATH . "git_commit";
			if(is_file($commitFile)){
				$hash = trim((string) file_get_contents($commitFile));
				if($hash !== "" && $hash !== str_repeat("00", 20)){
					return $short ? substr($hash, 0, 7) : $hash;
				}
			}

			return self::GIT_UNKNOWN;
		}

		$pharPath = \Phar::running(false);
		$phar = \Phar::isValidPharFilename($pharPath) ? new \Phar($pharPath) : new \PharData($pharPath);
		$meta = $phar->getMetadata();
		if(!isset($meta["git"])){
			return self::GIT_UNKNOWN;
		}

		$gitHash = (string) $meta["git"];
		if($gitHash === str_repeat("00", 20)){
			return self::GIT_UNKNOWN;
		}

		if($short){
			$base = str_contains($gitHash, "-dirty") ? substr($gitHash, 0, -6) : $gitHash;
			return substr($base, 0, 7) . (str_contains($gitHash, "-dirty") ? "-dirty" : "");
		}

		return $gitHash;
	}

	private static ?int $buildNumber = null;

	public static function BUILD_NUMBER() : int{
		if(self::$buildNumber === null){
			self::$buildNumber = 0;
			if(\Phar::running(true) !== ""){
				$pharPath = \Phar::running(false);
				$phar = \Phar::isValidPharFilename($pharPath) ? new \Phar($pharPath) : new \PharData($pharPath);
				$meta = $phar->getMetadata();
				if(is_array($meta) && isset($meta["build"]) && is_int($meta["build"])){
					self::$buildNumber = $meta["build"];
				}
			}
		}

		return self::$buildNumber;
	}

	private static ?VersionString $fullVersion = null;

	public static function VERSION() : VersionString{
		if(self::$fullVersion === null){
			self::$fullVersion = new VersionString(self::BASE_VERSION, self::IS_DEVELOPMENT_BUILD, self::BUILD_NUMBER());
		}
		return self::$fullVersion;
	}
}
