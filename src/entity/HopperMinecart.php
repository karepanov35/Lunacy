<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\tile\Container;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\object\ItemEntity;
use pocketmine\inventory\MinecartHopperInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;
use function count;
use function floor;

/** Hopper minecart — 5-slot container with item transfer logic. */
class HopperMinecart extends Minecart{

	private const INVENTORY_SIZE = 5;
	private const TRANSFER_COOLDOWN = 8;

	private MinecartHopperInventory $hopperInventory;
	private int $transferCooldown = 0;
	private bool $hopperEnabled = true;

	public static function getNetworkTypeId() : string{
		return EntityIds::HOPPER_MINECART;
	}

	public function getName() : string{ return "Minecart with Hopper"; }

	protected function initEntity(CompoundTag $nbt) : void{
		$this->hopperInventory = new MinecartHopperInventory($this);
		$this->hopperEnabled = $nbt->getByte("HopperEnabled", 1) === 1;
		parent::initEntity($nbt);

		if($nbt->getTag("Items") instanceof ListTag){
			/** @var ListTag<CompoundTag> $itemsTag */
			$itemsTag = $nbt->getListTag("Items");
			foreach($itemsTag as $itemTag){
				$item = Item::nbtDeserialize($itemTag);
				$slot = $itemTag->getByte("Slot", 0);
				if(!$item->isNull() && $slot < self::INVENTORY_SIZE){
					$this->hopperInventory->setItem($slot, $item);
				}
			}
		}
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$this->applyContainerMetadata($properties);
	}

	private function applyContainerMetadata(EntityMetadataCollection $properties) : void{
		$properties->setByte(EntityMetadataProperties::CONTAINER_TYPE, WindowTypes::MINECART_HOPPER);
		$properties->setInt(EntityMetadataProperties::CONTAINER_BASE_SIZE, self::INVENTORY_SIZE);
		$properties->setInt(EntityMetadataProperties::CONTAINER_EXTRA_SLOTS_PER_STRENGTH, 0);
	}

	protected function sendSpawnPacket(Player $player) : void{
		$typeConverter = $player->getNetworkSession()->getTypeConverter();
		$runtimeId = $typeConverter->getBlockTranslator()->internalIdToNetworkId(VanillaBlocks::HOPPER()->getStateId());

		$props = $this->getNetworkProperties();
		$this->applyContainerMetadata($props);
		$props->setByte(EntityMetadataProperties::MINECART_HAS_DISPLAY, 1);
		$props->setInt(EntityMetadataProperties::MINECART_DISPLAY_BLOCK, $runtimeId);
		$props->setInt(EntityMetadataProperties::MINECART_DISPLAY_OFFSET, 6);
		$props->clearDirtyProperties();

		parent::sendSpawnPacket($player);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte("HopperEnabled", $this->hopperEnabled ? 1 : 0);
		$itemList = new ListTag();
		foreach($this->hopperInventory->getContents() as $slot => $item){
			if(!$item->isNull()){
				$itemList->push($item->nbtSerialize($slot));
			}
		}
		$nbt->setTag("Items", $itemList);
		return $nbt;
	}

	public function getHopperInventory() : MinecartHopperInventory{
		return $this->hopperInventory;
	}

	public function isRideable() : bool{
		return false;
	}

	public function mountPlayer(Player $player) : void{
		$player->setCurrentWindow($this->hopperInventory);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$player->setCurrentWindow($this->hopperInventory);
		return false;
	}

	protected function onActivatorRail(int $x, int $y, int $z) : void{
		$this->hopperEnabled = false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		$this->checkAdjacentMinecartCollisions();
		if(!$this->isAlive() || !$this->hopperEnabled){
			return $hasUpdate;
		}

		if($this->transferCooldown > 0){
			$this->transferCooldown -= $tickDiff;
			return $hasUpdate;
		}

		if($this->pushItemsDown() || $this->pickupItems()){
			$this->transferCooldown = self::TRANSFER_COOLDOWN;
		}

		return $hasUpdate;
	}

	private function pushItemsDown() : bool{
		$bx = (int) floor($this->location->x);
		$by = (int) floor($this->location->y) - 1;
		$bz = (int) floor($this->location->z);

		$tile = $this->getWorld()->getTile(new Vector3($bx, $by, $bz));
		if(!($tile instanceof Container)){
			return false;
		}

		$targetInv = $tile->getInventory();
		foreach($this->hopperInventory->getContents() as $slot => $item){
			if($item->isNull()){
				continue;
			}
			$toAdd = clone $item;
			$toAdd->setCount(1);
			if(!$targetInv->canAddItem($toAdd)){
				continue;
			}
			if(count($targetInv->addItem($toAdd)) === 0){
				$item->setCount($item->getCount() - 1);
				$this->hopperInventory->setItem($slot, $item->getCount() > 0 ? $item : VanillaItems::AIR());
				return true;
			}
		}
		return false;
	}

	private function pickupItems() : bool{
		$bb = new AxisAlignedBB(
			$this->location->x - 0.5,
			$this->location->y,
			$this->location->z - 0.5,
			$this->location->x + 0.5,
			$this->location->y + 1.5,
			$this->location->z + 0.5
		);

		foreach($this->getWorld()->getNearbyEntities($bb, $this) as $entity){
			if(!($entity instanceof ItemEntity) || $entity->isFlaggedForDespawn()){
				continue;
			}
			$item = clone $entity->getItem();
			if($item->isNull()){
				continue;
			}
			$leftover = $this->hopperInventory->addItem($item);
			$firstLeft = array_shift($leftover);
			$taken = $item->getCount() - ($firstLeft?->getCount() ?? 0);
			if($taken > 0){
				$remaining = $item->getCount() - $taken;
				if($remaining <= 0){
					$entity->flagForDespawn();
				}else{
					$entity->getItem()->setCount($remaining);
				}
				return true;
			}
		}
		return false;
	}

	protected function onDeath() : void{
		foreach($this->hopperInventory->getContents() as $item){
			$this->getWorld()->dropItem($this->location, $item);
		}
		$this->hopperInventory->clearAll();
		parent::onDeath();
	}

	protected function dropMinecartItem() : void{
		$this->getWorld()->dropItem($this->location, VanillaItems::HOPPER_MINECART());
	}
}
