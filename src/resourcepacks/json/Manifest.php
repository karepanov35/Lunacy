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
namespace pocketmine\resourcepacks\json;

/**
 * Model for JsonMapper to represent resource pack manifest.json contents.
 */
final class Manifest{
	/** @required */
	public int $format_version;

	/** @required */
	public ManifestHeader $header;

	/**
	 * @var ManifestModuleEntry[]
	 * @required
	 */
	public array $modules;

	public ?ManifestMetadata $metadata = null;

	/** @var string[] */
	public ?array $capabilities = null;

	/** @var ManifestDependencyEntry[] */
	public ?array $dependencies = null;
}
