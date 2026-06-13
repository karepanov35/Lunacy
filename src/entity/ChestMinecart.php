<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\MinecartChestInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;

/** Storage minecart — 27-slot container, not rideable. */
class ChestMinecart extends Minecart{

	private const INVENTORY_SIZE = 27;

	private MinecartChestInventory $chestInventory;

	public static function getNetworkTypeId() : string{
		return EntityIds::CHEST_MINECART;
	}

	public function getName() : string{ return "Minecart with Chest"; }

	protected function initEntity(CompoundTag $nbt) : void{
		$this->chestInventory = new MinecartChestInventory($this);
		parent::initEntity($nbt);

		if($nbt->getTag("Items") instanceof ListTag){
			/** @var ListTag<CompoundTag> $items */
			$items = $nbt->getListTag("Items");
			foreach($items as $itemTag){
				$item = Item::nbtDeserialize($itemTag);
				$slot = $itemTag->getByte("Slot", 0);
				if(!$item->isNull() && $slot < self::INVENTORY_SIZE){
					$this->chestInventory->setItem($slot, $item);
				}
			}
		}
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$this->applyContainerMetadata($properties);
	}

	private function applyContainerMetadata(EntityMetadataCollection $properties) : void{
		$properties->setByte(EntityMetadataProperties::CONTAINER_TYPE, WindowTypes::MINECART_CHEST);
		$properties->setInt(EntityMetadataProperties::CONTAINER_BASE_SIZE, self::INVENTORY_SIZE);
		$properties->setInt(EntityMetadataProperties::CONTAINER_EXTRA_SLOTS_PER_STRENGTH, 0);
	}

	protected function sendSpawnPacket(Player $player) : void{
		$typeConverter = $player->getNetworkSession()->getTypeConverter();
		$runtimeId = $typeConverter->getBlockTranslator()->internalIdToNetworkId(VanillaBlocks::CHEST()->getStateId());

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
		$itemList = new ListTag();
		foreach($this->chestInventory->getContents() as $slot => $item){
			if(!$item->isNull()){
				$itemList->push($item->nbtSerialize($slot));
			}
		}
		$nbt->setTag("Items", $itemList);
		return $nbt;
	}

	public function getChestInventory() : MinecartChestInventory{
		return $this->chestInventory;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		$this->checkAdjacentMinecartCollisions();
		return $hasUpdate;
	}

	public function isRideable() : bool{
		return false;
	}

	public function mountPlayer(Player $player) : void{
		$player->setCurrentWindow($this->chestInventory);
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$player->setCurrentWindow($this->chestInventory);
		return false;
	}

	protected function onDeath() : void{
		foreach($this->chestInventory->getContents() as $item){
			$this->getWorld()->dropItem($this->location, $item);
		}
		$this->chestInventory->clearAll();
		parent::onDeath();
	}

	protected function dropMinecartItem() : void{
		$this->getWorld()->dropItem($this->location, VanillaItems::CHEST_MINECART());
	}
}
