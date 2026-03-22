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
namespace pocketmine\item;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\FlintSteelSound;

class FlintSteel extends Tool{

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		$world = $player->getWorld();
		
		// Проверяем активацию портала в ад
		if($blockClicked->getTypeId() === BlockTypeIds::OBSIDIAN){
			// Пытаемся создать портал
			// $blockReplace is the adjacent block in the clicked face direction, but portal ignition should be based on the
			// actual obsidian block position that was clicked.
			if($this->tryCreateNetherPortal($world, $blockClicked->getPosition())){
				$world->addSound($blockClicked->getPosition()->add(0.5, 0.5, 0.5), new FlintSteelSound());
				$this->applyDamage(1);
				return ItemUseResult::SUCCESS;
			}
		}
		
		// Обычное поджигание
		if($blockReplace->getTypeId() === BlockTypeIds::AIR){
			$world->setBlock($blockReplace->getPosition(), VanillaBlocks::FIRE());
			$world->addSound($blockReplace->getPosition()->add(0.5, 0.5, 0.5), new FlintSteelSound());

			$this->applyDamage(1);

			return ItemUseResult::SUCCESS;
		}

		return ItemUseResult::NONE;
	}

	private function tryCreateNetherPortal(\pocketmine\world\World $world, Vector3 $pos) : bool{
		// Проверяем вертикальный портал (ось X)
		if($this->checkAndCreatePortal($world, $pos, true)){
			return true;
		}
		
		// Проверяем вертикальный портал (ось Z)
		if($this->checkAndCreatePortal($world, $pos, false)){
			return true;
		}
		
		return false;
	}

	private function checkAndCreatePortal(\pocketmine\world\World $world, Vector3 $pos, bool $axisX) : bool{
		// Ищем нижний левый угол портала
		$basePos = $pos->floor();
		
		// Проверяем разные размеры порталов (минимум 4x5, максимум 23x23)
		for($width = 4; $width <= 23; $width++){
			for($height = 5; $height <= 23; $height++){
				// Пробуем разные позиции для нижнего левого угла
				for($offsetX = -$width + 1; $offsetX <= 1; $offsetX++){
					for($offsetY = -$height + 1; $offsetY <= 1; $offsetY++){
						$corner = $basePos->add($axisX ? $offsetX : 0, $offsetY, $axisX ? 0 : $offsetX);
						
						if($this->isValidPortalFrame($world, $corner, $width, $height, $axisX)){
							$this->fillPortal($world, $corner, $width, $height, $axisX);
							return true;
						}
					}
				}
			}
		}
		
		return false;
	}

	private function isValidPortalFrame(\pocketmine\world\World $world, Vector3 $corner, int $width, int $height, bool $axisX) : bool{
		// Проверяем рамку из обсидиана
		for($y = 0; $y < $height; $y++){
			for($w = 0; $w < $width; $w++){
				$x = $axisX ? $corner->x + $w : $corner->x;
				$z = $axisX ? $corner->z : $corner->z + $w;
				$checkPos = new Vector3($x, $corner->y + $y, $z);
				
				$isEdge = ($y === 0 || $y === $height - 1 || $w === 0 || $w === $width - 1);
				$block = $world->getBlockAt((int)$checkPos->x, (int)$checkPos->y, (int)$checkPos->z);
				
				if($isEdge){
					// Края должны быть обсидианом
					if($block->getTypeId() !== BlockTypeIds::OBSIDIAN){
						return false;
					}
				}else{
					// Внутри должен быть воздух
					if($block->getTypeId() !== BlockTypeIds::AIR){
						return false;
					}
				}
			}
		}
		
		return true;
	}

	private function fillPortal(\pocketmine\world\World $world, Vector3 $corner, int $width, int $height, bool $axisX) : void{
		// Заполняем портал блоками портала
		$axis = $axisX ? \pocketmine\math\Axis::X : \pocketmine\math\Axis::Z;
		
		for($y = 1; $y < $height - 1; $y++){
			for($w = 1; $w < $width - 1; $w++){
				$x = $axisX ? $corner->x + $w : $corner->x;
				$z = $axisX ? $corner->z : $corner->z + $w;
				$fillPos = new Vector3($x, $corner->y + $y, $z);
				
				$portal = VanillaBlocks::NETHER_PORTAL()->setAxis($axis);
				$world->setBlock($fillPos, $portal);
			}
		}
		
		// Звук активации портала
		$world->addSound($corner->add($width / 2, $height / 2, $width / 2), new \pocketmine\world\sound\BlazeShootSound());
	}

	public function getMaxDurability() : int{
		return 65;
	}
}
