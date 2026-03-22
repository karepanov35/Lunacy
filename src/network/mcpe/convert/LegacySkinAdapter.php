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
namespace pocketmine\network\mcpe\convert;

use pocketmine\entity\InvalidSkinException;
use pocketmine\entity\Skin;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function random_bytes;
use function str_repeat;
use const JSON_THROW_ON_ERROR;

class LegacySkinAdapter implements SkinAdapter{

	public function toSkinData(Skin $skin) : SkinData{
		// Используем полные данные скина (Persona, анимации, моргающие глаза) если они были сохранены
		$fullData = $skin->getFullSkinData();
		if($fullData !== null){
			return $fullData;
		}

		$capeData = $skin->getCapeData();
		$capeImage = $capeData === "" ? new SkinImage(0, 0, "") : new SkinImage(32, 64, $capeData);
		$geometryName = $skin->getGeometryName();
		if($geometryName === ""){
			$geometryName = "geometry.humanoid.custom";
		}
		
		// Определяем размер скина по длине данных
		$skinData = $skin->getSkinData();
		$skinDataLength = strlen($skinData);
		
		// Вычисляем ширину и высоту на основе размера данных (RGBA = 4 байта на пиксель)
		$pixelCount = $skinDataLength / 4;
		
		// Определяем размеры скина
		if($pixelCount === 64 * 32) {
			$width = 64;
			$height = 32;
		} elseif($pixelCount === 64 * 64) {
			$width = 64;
			$height = 64;
		} elseif($pixelCount === 128 * 64) {
			$width = 128;
			$height = 64;
		} elseif($pixelCount === 128 * 128) {
			$width = 128;
			$height = 128;
		} elseif($pixelCount === 256 * 128) {
			$width = 256;
			$height = 128;
		} elseif($pixelCount === 256 * 256) {
			$width = 256;
			$height = 256;
		} elseif($pixelCount === 512 * 256) {
			$width = 512;
			$height = 256;
		} elseif($pixelCount === 512 * 512) {
			$width = 512;
			$height = 512;
		} elseif($pixelCount === 1024 * 512) {
			$width = 1024;
			$height = 512;
		} elseif($pixelCount === 1024 * 1024) {
			$width = 1024;
			$height = 1024;
		} else {
			// Если размер неизвестен, используем стандартный 64x64
			$width = 64;
			$height = 64;
			// Подгоняем данные под 64x64
			$skinData = str_repeat("\x00\x00\x00\xff", 64 * 64);
		}
		
		$skinImage = new SkinImage($width, $height, $skinData);
		
		return new SkinData(
			$skin->getSkinId(),
			"", //TODO: playfab ID
			json_encode(["geometry" => ["default" => $geometryName]], JSON_THROW_ON_ERROR),
			$skinImage, [],
			$capeImage,
			$skin->getGeometryData()
		);
	}

	public function fromSkinData(SkinData $data) : Skin{
		// Убираем проверку isPersona() - используем реальные данные скина всегда
		// Это позволит дефолтным скинам Minecraft (Steve, Alex и т.д.) отображаться правильно
		
		// Всегда передаём данные плаща — не обнуляем по CapeOnClassicSkin, чтобы плащи отображались
		$capeData = $data->getCapeImage()->getData();

		$resourcePatch = json_decode($data->getResourcePatch(), true);
		if(is_array($resourcePatch) && isset($resourcePatch["geometry"]["default"]) && is_string($resourcePatch["geometry"]["default"])){
			$geometryName = $resourcePatch["geometry"]["default"];
		}else{
			// Если нет геометрии, используем стандартную
			$geometryName = "geometry.humanoid.custom";
		}

		$skin = new Skin($data->getSkinId(), $data->getSkinImage()->getData(), $capeData, $geometryName, $data->getGeometryData());
		$skin->setFullSkinData($data);
		return $skin;
	}
}
