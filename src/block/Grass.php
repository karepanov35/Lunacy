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
namespace pocketmine\block;

use pocketmine\block\utils\BlockEventHelper;
use pocketmine\block\utils\DirtType;
use pocketmine\item\Fertilizer;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Shovel;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\generator\object\TallGrass as TallGrassObject;
use pocketmine\world\sound\ItemUseOnBlockSound;
use function mt_rand;

class Grass extends Opaque{

	public function getDropsForCompatibleTool(Item $item) : array{
		return [
			VanillaBlocks::DIRT()->asItem()
		];
	}

	public function isAffectedBySilkTouch() : bool{
		return true;
	}

	public function ticksRandomly() : bool{
		return true;
	}

	public function onRandomTick() : void{
		$world = $this->position->getWorld();
		$lightAbove = $world->getFullLightAt($this->position->x, $this->position->y + 1, $this->position->z);
		if($lightAbove < 4 && $world->getBlockAt($this->position->x, $this->position->y + 1, $this->position->z)->getLightFilter() >= 2){
			//grass dies
			BlockEventHelper::spread($this, VanillaBlocks::DIRT(), $this);
		}elseif($lightAbove >= 9){
			//try grass spread
			for($i = 0; $i < 4; ++$i){
				$x = mt_rand($this->position->x - 1, $this->position->x + 1);
				$y = mt_rand($this->position->y - 3, $this->position->y + 1);
				$z = mt_rand($this->position->z - 1, $this->position->z + 1);

				$b = $world->getBlockAt($x, $y, $z);
				if(
					!($b instanceof Dirt) ||
					$b->getDirtType() !== DirtType::NORMAL ||
					$world->getFullLightAt($x, $y + 1, $z) < 4 ||
					$world->getBlockAt($x, $y + 1, $z)->getLightFilter() >= 2
				){
					continue;
				}

				BlockEventHelper::spread($b, VanillaBlocks::GRASS(), $this);
			}
		}
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($this->getSide(Facing::UP)->getTypeId() !== BlockTypeIds::AIR){
			return false;
		}
		$world = $this->position->getWorld();
		if($item instanceof Fertilizer){
			$item->pop();
			$random = new Random(mt_rand());
			$pos = $this->position;
			// TerrainObject\TallGrass: instance + generate() (старый статический growGrass() отсутствует)
			$plant = (mt_rand() & 1) === 0 ? VanillaBlocks::TALL_GRASS() : VanillaBlocks::FERN();
			(new TallGrassObject($plant))->generate($world, $random, $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());

			return true;
		}
		if($face !== Facing::DOWN){
			if($item instanceof Hoe){
				$item->applyDamage(1);
				$newBlock = VanillaBlocks::FARMLAND();
				$world->addSound($this->position->add(0.5, 0.5, 0.5), new ItemUseOnBlockSound($newBlock));
				$world->setBlock($this->position, $newBlock);

				return true;
			}elseif($item instanceof Shovel){
				$item->applyDamage(1);
				$newBlock = VanillaBlocks::GRASS_PATH();
				$world->addSound($this->position->add(0.5, 0.5, 0.5), new ItemUseOnBlockSound($newBlock));
				$world->setBlock($this->position, $newBlock);

				return true;
			}
		}

		return false;
	}
}
