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

	use function is_dir;

	$CoreConstants_srcRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR;

	$resolveComposerPackagePath = static function(string $packageName, string $vendorSubdir) use ($CoreConstants_srcRoot) : string{
		if(InstalledVersions::isInstalled($packageName)){
			return InstalledVersions::getInstallPath($packageName);
		}
		$p = $CoreConstants_srcRoot . 'vendor' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $vendorSubdir);
		if(is_dir($p)){
			return $p;
		}
		throw new \RuntimeException(
			"Package \"$packageName\" is missing. Expected under vendor/$vendorSubdir — run: composer install"
		);
	};

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
		$bedrockData = null;
		if(InstalledVersions::isInstalled('pocketmine/bedrock-data')){
			$bedrockData = InstalledVersions::getInstallPath('pocketmine/bedrock-data');
		}elseif(InstalledVersions::isInstalled('nethergamesmc/bedrock-data')){
			$bedrockData = InstalledVersions::getInstallPath('nethergamesmc/bedrock-data');
		}else{
			$vendor = $CoreConstants_srcRoot . 'vendor' . DIRECTORY_SEPARATOR;
			foreach(['pocketmine/bedrock-data', 'nethergamesmc/bedrock-data'] as $rel){
				$p = $vendor . str_replace('/', DIRECTORY_SEPARATOR, $rel);
				if(is_dir($p)){
					$bedrockData = $p;
					break;
				}
			}
		}
		if($bedrockData === null || $bedrockData === ''){
			throw new \RuntimeException('bedrock-data not found. Run: composer install');
		}
		define('pocketmine\BEDROCK_DATA_PATH', $bedrockData . DIRECTORY_SEPARATOR);
	}
	if(!defined('pocketmine\BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH')){
		define(
			'pocketmine\BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH',
			$resolveComposerPackagePath('pocketmine/bedrock-block-upgrade-schema', 'pocketmine/bedrock-block-upgrade-schema') . DIRECTORY_SEPARATOR
		);
	}
	if(!defined('pocketmine\BEDROCK_ITEM_UPGRADE_SCHEMA_PATH')){
		define(
			'pocketmine\BEDROCK_ITEM_UPGRADE_SCHEMA_PATH',
			$resolveComposerPackagePath('pocketmine/bedrock-item-upgrade-schema', 'pocketmine/bedrock-item-upgrade-schema') . DIRECTORY_SEPARATOR
		);
	}
}
