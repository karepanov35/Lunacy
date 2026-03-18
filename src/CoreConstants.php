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
namespace pocketmine {

	use Composer\InstalledVersions;

	$CoreConstants_srcRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR;

	if(!defined('pocketmine\PATH')){
		define('pocketmine\PATH', $CoreConstants_srcRoot);
	}
	if(!defined('pocketmine\RESOURCE_PATH')){
		define('pocketmine\RESOURCE_PATH', $CoreConstants_srcRoot . 'resources' . DIRECTORY_SEPARATOR);
	}
	if(!defined('pocketmine\COMPOSER_AUTOLOADER_PATH')){
		define('pocketmine\COMPOSER_AUTOLOADER_PATH', $CoreConstants_srcRoot . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');
	}
	if(!defined('pocketmine\LOCALE_DATA_PATH')){
		define('pocketmine\LOCALE_DATA_PATH', RESOURCE_PATH . 'translations' . DIRECTORY_SEPARATOR);
	}
	if(!defined('pocketmine\BEDROCK_DATA_PATH')){
		define('pocketmine\BEDROCK_DATA_PATH', InstalledVersions::getInstallPath('nethergamesmc/bedrock-data') . DIRECTORY_SEPARATOR);
	}
	if(!defined('pocketmine\BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH')){
		define('pocketmine\BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH', InstalledVersions::getInstallPath('pocketmine/bedrock-block-upgrade-schema') . DIRECTORY_SEPARATOR);
	}
	if(!defined('pocketmine\BEDROCK_ITEM_UPGRADE_SCHEMA_PATH')){
		define('pocketmine\BEDROCK_ITEM_UPGRADE_SCHEMA_PATH', InstalledVersions::getInstallPath('pocketmine/bedrock-item-upgrade-schema') . DIRECTORY_SEPARATOR);
	}
}
