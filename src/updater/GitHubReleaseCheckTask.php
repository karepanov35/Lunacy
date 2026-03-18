<?php


/*
 *
 *
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█ 
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█ 
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 *
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
namespace pocketmine\updater;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\VersionInfo;
use pocketmine\utils\Internet;
use function json_decode;
use function ltrim;
use function parse_url;
use function preg_replace;
use function version_compare;

class GitHubReleaseCheckTask extends AsyncTask{
	private const TLS_KEY_SERVER = "server";

	public function __construct(Server $server){
		$this->storeLocal(self::TLS_KEY_SERVER, $server);
	}

	public function onRun() : void{
		$path = parse_url(VersionInfo::GITHUB_URL, PHP_URL_PATH);
		if($path === null || $path === ""){
			$this->setResult(["outdated" => false]);
			return;
		}
		$repo = ltrim($path, "/");
		$apiUrl = "https://api.github.com/repos/" . $repo . "/releases/latest";
		$error = "";
		$response = Internet::getURL($apiUrl, 5, [
			"User-Agent: " . VersionInfo::NAME . "/" . VersionInfo::BASE_VERSION,
			"Accept: application/vnd.github.v3+json"
		], $error);

		if($response === null || $response->getCode() !== 200){
			$this->setResult(["outdated" => false]);
			return;
		}

		$data = json_decode($response->getBody(), true);
		if(!isset($data["tag_name"]) || !is_string($data["tag_name"])){
			$this->setResult(["outdated" => false]);
			return;
		}

		$latest = preg_replace('/^v/', '', $data["tag_name"]);
		$current = VersionInfo::BASE_VERSION;
		$outdated = version_compare($current, $latest, "<");

		$this->setResult([
			"outdated" => $outdated,
			"releases_url" => VersionInfo::GITHUB_URL . "/releases"
		]);
	}

	public function onCompletion() : void{
		/** @var Server $server */
		$server = $this->fetchLocal(self::TLS_KEY_SERVER);
		$result = $this->getResult();
		if(!is_array($result) || empty($result["outdated"])){
			return;
		}
		$releasesUrl = $result["releases_url"] ?? VersionInfo::GITHUB_URL . "/releases";
		$message = $server->getLanguage()->translateString("pocketmine.lunacy.version.outdated", [$releasesUrl]);
		$server->getLogger()->warning($message);
	}
}
