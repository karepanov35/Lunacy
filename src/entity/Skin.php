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
namespace pocketmine\entity;

use Ahc\Json\Comment as CommentedJsonDecoder;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\utils\Limits;
use function implode;
use function in_array;
use function json_encode;
use function strlen;
use const JSON_THROW_ON_ERROR;

final class Skin{
	public const ACCEPTED_SKIN_SIZES = [
		64 * 32 * 4,      // Старый формат (Steve)
		64 * 64 * 4,      // Стандартный формат
		128 * 64 * 4,     // HD скины (128x64)
		128 * 128 * 4,    // HD скины (128x128)
		256 * 128 * 4,    // Ultra HD скины (256x128)
		256 * 256 * 4,    // Ultra HD скины (256x256)
		512 * 256 * 4,    // 4K скины (512x256)
		512 * 512 * 4,    // 4K скины (512x512)
		1024 * 512 * 4,   // 8K скины (1024x512)
		1024 * 1024 * 4,  // 8K скины (1024x1024)
	];

	private string $skinId;
	private string $skinData;
	private string $capeData;
	private string $geometryName;
	private string $geometryData;

	/** Полные данные скина для Persona (моргающие глаза, анимации и т.д.) - сохраняются при fromSkinData для корректной рассылки */
	private ?SkinData $fullSkinData = null;

	private static function checkLength(string $string, string $name, int $maxLength) : void{
		// ВАЛИДАЦИЯ ДЛИНЫ ПОЛНОСТЬЮ ОТКЛЮЧЕНА - ПРИНИМАЕМ ЛЮБУЮ ДЛИНУ
		return;
		
		if(strlen($string) > $maxLength){
			throw new InvalidSkinException("$name must be at most $maxLength bytes, but have " . strlen($string) . " bytes");
		}
	}

	/**
	 * Находит ближайший подходящий размер скина
	 */
	private static function findClosestSkinSize(int $actualSize) : ?int{
		$closestSize = null;
		$minDiff = PHP_INT_MAX;
		
		foreach(self::ACCEPTED_SKIN_SIZES as $size){
			$diff = abs($actualSize - $size);
			if($diff < $minDiff){
				$minDiff = $diff;
				$closestSize = $size;
			}
		}
		
		// Возвращаем только если разница не слишком большая (не более 50%)
		if($closestSize !== null && $minDiff <= $closestSize * 0.5){
			return $closestSize;
		}
		
		return null;
	}

	public function __construct(string $skinId, string $skinData, string $capeData = "", string $geometryName = "", string $geometryData = ""){
		self::checkLength($skinId, "Skin ID", Limits::INT16_MAX);
		self::checkLength($geometryName, "Geometry name", Limits::INT16_MAX);
		self::checkLength($geometryData, "Geometry data", Limits::INT32_MAX);

		if($skinId === ""){
			// Генерируем уникальный ID если он пустой
			$skinId = "Standard_Custom_" . bin2hex(random_bytes(4));
		}
		
		// ПОЛНОСТЬЮ ОТКЛЮЧАЕМ ВАЛИДАЦИЮ РАЗМЕРА СКИНА - ПРИНИМАЕМ ВСЕ
		// Больше никаких проверок размера!
		
		// ПОЛНОСТЬЮ ОТКЛЮЧАЕМ ВАЛИДАЦИЮ ПЛАЩА - ПРИНИМАЕМ ВСЕ
		// Плащ любого размера теперь валиден

		// ПОЛНОСТЬЮ ОТКЛЮЧАЕМ ВАЛИДАЦИЮ ГЕОМЕТРИИ - ПРИНИМАЕМ ВСЕ
		if($geometryData !== ""){
			try{
				// Пытаемся декодировать и минифицировать JSON
				$decodedGeometry = (new CommentedJsonDecoder())->decode($geometryData);
				$geometryData = json_encode($decodedGeometry, JSON_THROW_ON_ERROR);
			}catch(\RuntimeException | \JsonException $e){
				// Если не удалось - оставляем как есть, не выбрасываем ошибку
				// Геометрия будет использована как есть
			}
		}
		
		// Если нет имени геометрии, используем стандартное
		if($geometryName === ""){
			$geometryName = "geometry.humanoid.custom";
		}

		$this->skinId = $skinId;
		$this->skinData = $skinData;
		$this->capeData = $capeData;
		$this->geometryName = $geometryName;
		$this->geometryData = $geometryData;
	}

	public function getSkinId() : string{
		return $this->skinId;
	}

	public function getSkinData() : string{
		return $this->skinData;
	}

	public function getCapeData() : string{
		return $this->capeData;
	}

	public function getGeometryName() : string{
		return $this->geometryName;
	}

	public function getGeometryData() : string{
		return $this->geometryData;
	}

	public function getFullSkinData() : ?SkinData{
		return $this->fullSkinData;
	}

	public function setFullSkinData(?SkinData $data) : void{
		$this->fullSkinData = $data;
	}
}
