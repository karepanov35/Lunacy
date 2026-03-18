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
namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;

class SeedCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"seed",
			KnownTranslationFactory::pocketmine_command_seed_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SEED);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if($sender instanceof Player){
			$seed = $sender->getPosition()->getWorld()->getSeed();
		}else{
			$seed = $sender->getServer()->getWorldManager()->getDefaultWorld()->getSeed();
		}
		$sender->sendMessage(KnownTranslationFactory::commands_seed_success((string) $seed));

		return true;
	}
}
