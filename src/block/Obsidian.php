<?php

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\SupportType;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class Obsidian extends Opaque{

	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{
		$result = parent::onBreak($item, $player, $returnedItems);
		
		if($result){
			// Проверяем и удаляем портал
			$this->checkAndDestroyPortal();
		}
		
		return $result;
	}

	private function checkAndDestroyPortal() : void{
		$world = $this->position->getWorld();
		
		// Проверяем все 6 направлений от сломанного обсидиана
		foreach(Facing::ALL as $facing){
			$side = $this->position->getSide($facing);
			$block = $world->getBlockAt((int)$side->x, (int)$side->y, (int)$side->z);
			
			// Если рядом портал - удаляем всю структуру портала
			if($block->getTypeId() === BlockTypeIds::NETHER_PORTAL){
				$this->destroyPortalStructure($side);
				return;
			}
		}
	}

	private function destroyPortalStructure(Vector3 $portalPos) : void{
		$world = $this->position->getWorld();
		$checked = [];
		$toCheck = [$portalPos];
		
		// Flood fill - удаляем все связанные блоки портала
		while(!empty($toCheck)){
			$pos = array_shift($toCheck);
			$key = $pos->x . ":" . $pos->y . ":" . $pos->z;
			
			if(isset($checked[$key])){
				continue;
			}
			
			$checked[$key] = true;
			$block = $world->getBlockAt((int)$pos->x, (int)$pos->y, (int)$pos->z);
			
			if($block->getTypeId() === BlockTypeIds::NETHER_PORTAL){
				// Удаляем блок портала
				$world->setBlock($pos, VanillaBlocks::AIR());
				
				// Добавляем соседние блоки для проверки
				foreach(Facing::ALL as $facing){
					$side = $pos->getSide($facing);
					$sideKey = $side->x . ":" . $side->y . ":" . $side->z;
					if(!isset($checked[$sideKey])){
						$toCheck[] = $side;
					}
				}
			}
		}
	}
}
