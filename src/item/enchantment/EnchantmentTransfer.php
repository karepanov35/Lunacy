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
namespace pocketmine\item\enchantment;

final class EnchantmentTransfer{

	private const RARITY_TO_MULTIPLIER = [
		Rarity::COMMON => 1,
		Rarity::UNCOMMON => 1,
		Rarity::RARE => 2,
		Rarity::MYTHIC => 4,
	];
	private const SOURCE_RARITY_TO_MULTIPLIER = [
		Rarity::COMMON => 1,
		Rarity::UNCOMMON => 2,
		Rarity::RARE => 2,
		Rarity::MYTHIC => 2,
	];

	private function __construct(){
		//NOOP
	}

	public static function getCost(Enchantment $type, int $levelDifference, bool $transferFromItem) : int{
		$rarity = $type->getRarity();
		$cost = self::RARITY_TO_MULTIPLIER[$rarity] * $levelDifference;
		if($transferFromItem){
			$cost *= self::SOURCE_RARITY_TO_MULTIPLIER[$rarity];
		}
		return $cost;
	}
}
