<?php

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\entity\Location;
use pocketmine\entity\object\FishingHook;
use pocketmine\entity\projectile\Projectile;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;

class FishingRod extends Durable implements Releasable{

	public function getMaxStackSize() : int{
		return 1;
	}

	public function getMaxDurability() : int{
		return 384;
	}

	public function canStartUsingItem(Player $player) : bool{
		return true;
	}

	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems) : ItemUseResult{
		foreach($player->getWorld()->getEntities() as $entity){
			if($entity instanceof FishingHook && $entity->getOwningEntity() === $player && !$entity->isFlaggedForDespawn()){
				$entity->reelIn();
				return ItemUseResult::SUCCESS;
			}
		}

		$location = $player->getLocation();
		
		$hook = new FishingHook(
			Location::fromObject(
				$player->getEyePos(),
				$player->getWorld(),
				($location->yaw > 180 ? 360 : 0) - $location->yaw,
				-$location->pitch
			),
			$player
		);

		$hook->setMotion($directionVector->multiply(0.4));
		$hook->spawnToAll();

		$player->getWorld()->addSound($player->getLocation(), new ThrowSound());

		return ItemUseResult::SUCCESS;
	}

	public function onReleaseUsing(Player $player, array &$returnedItems) : ItemUseResult{
		foreach($player->getWorld()->getEntities() as $entity){
			if($entity instanceof FishingHook && $entity->getOwningEntity() === $player && !$entity->isFlaggedForDespawn()){
				$entity->reelIn();
				break;
			}
		}
		return ItemUseResult::SUCCESS;
	}

	public function getCooldownTicks() : int{
		return 10;
	}
}

