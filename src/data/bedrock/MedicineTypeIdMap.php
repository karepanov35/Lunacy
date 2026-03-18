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
namespace pocketmine\data\bedrock;

use pocketmine\item\MedicineType;
use pocketmine\utils\SingletonTrait;

final class MedicineTypeIdMap{
	use SingletonTrait;
	/** @phpstan-use IntSaveIdMapTrait<MedicineType> */
	use IntSaveIdMapTrait;

	private function __construct(){
		foreach(MedicineType::cases() as $case){
			$this->register(match($case){
				MedicineType::ANTIDOTE => MedicineTypeIds::ANTIDOTE,
				MedicineType::ELIXIR => MedicineTypeIds::ELIXIR,
				MedicineType::EYE_DROPS => MedicineTypeIds::EYE_DROPS,
				MedicineType::TONIC => MedicineTypeIds::TONIC,
			}, $case);
		}
	}
}
