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
namespace pocketmine\inventory;

use pocketmine\crafting\CraftingManagerFromDataHelper;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\block\BlockTypeIds;
use pocketmine\inventory\json\CreativeGroupData;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\lang\Translatable;
use pocketmine\utils\DestructorCallbackTrait;
use pocketmine\utils\ObjectSet;
use pocketmine\utils\SingletonTrait;
use Symfony\Component\Filesystem\Path;
use const pocketmine\RESOURCE_PATH;
use function array_filter;
use function array_map;
use function array_values;
use function is_file;

final class CreativeInventory{
	use SingletonTrait;
	use DestructorCallbackTrait;

	/**
	 * @var CreativeInventoryEntry[]
	 * @phpstan-var array<int, CreativeInventoryEntry>
	 */
	private array $creative = [];

	/** @phpstan-var ObjectSet<\Closure() : void> */
	private ObjectSet $contentChangedCallbacks;

	private function __construct(){
		$this->contentChangedCallbacks = new ObjectSet();

		foreach([
			"construction" => CreativeCategory::CONSTRUCTION,
			"nature" => CreativeCategory::NATURE,
			"equipment" => CreativeCategory::EQUIPMENT,
			"items" => CreativeCategory::ITEMS,
		] as $categoryId => $categoryEnum){
			$groups = CraftingManagerFromDataHelper::loadJsonArrayOfObjectsFile(
				Path::join(BedrockDataFiles::CREATIVE, $categoryId . ".json"),
				CreativeGroupData::class
			);

			foreach($groups as $groupData){
				$icon = $groupData->group_icon === null ? null : CraftingManagerFromDataHelper::deserializeItemStack($groupData->group_icon);

				$group = $icon === null ? null : new CreativeGroup(
					new Translatable($groupData->group_name),
					$icon
				);

				$items = array_filter(array_map(static fn($itemStack) => CraftingManagerFromDataHelper::deserializeItemStack($itemStack), $groupData->items));

				foreach($items as $item){
					$this->add($item, $categoryEnum, $group);
				}
			}
		}

		// Доп. предметы креатива из ядра (resources/creative/*.json) — не зависят от vendor bedrock-data
		$lunacyCreative = Path::join(RESOURCE_PATH, 'creative', 'items.json');
		if(is_file($lunacyCreative)){
			$extraGroups = CraftingManagerFromDataHelper::loadJsonArrayOfObjectsFile(
				$lunacyCreative,
				CreativeGroupData::class
			);
			foreach($extraGroups as $groupData){
				$icon = $groupData->group_icon === null ? null : CraftingManagerFromDataHelper::deserializeItemStack($groupData->group_icon);
				$group = $icon === null ? null : new CreativeGroup(
					new Translatable($groupData->group_name),
					$icon
				);
				$items = array_filter(array_map(static fn($itemStack) => CraftingManagerFromDataHelper::deserializeItemStack($itemStack), $groupData->items));
				foreach($items as $item){
					if($this->getItemIndex($item) === -1){
						$this->add($item, CreativeCategory::ITEMS, $group);
					}
				}
			}
		}

		// Яйцо и яйцо призыва курицы (часто отсутствуют в bedrock-data creative JSON для форков)
		if(!$this->contains(VanillaItems::EGG())){
			$this->add(VanillaItems::EGG(), CreativeCategory::ITEMS);
		}
		if(!$this->contains(VanillaItems::CHICKEN_SPAWN_EGG())){
			$this->add(VanillaItems::CHICKEN_SPAWN_EGG(), CreativeCategory::NATURE);
		}
		if(!$this->contains(VanillaItems::WOLF_SPAWN_EGG())){
			$this->add(VanillaItems::WOLF_SPAWN_EGG(), CreativeCategory::NATURE);
		}
		if(!$this->contains(VanillaItems::HOGLIN_SPAWN_EGG())){
			$this->add(VanillaItems::HOGLIN_SPAWN_EGG(), CreativeCategory::NATURE);
		}
		if(!$this->contains(VanillaItems::ZOGLIN_SPAWN_EGG())){
			$this->add(VanillaItems::ZOGLIN_SPAWN_EGG(), CreativeCategory::NATURE);
		}
		if(!$this->contains(VanillaItems::LEAD())){
			$this->add(VanillaItems::LEAD(), CreativeCategory::EQUIPMENT);
		}
		// Око края — запасной вариант, если JSON не загрузился
		if(!$this->contains(VanillaItems::ENDER_EYE())){
			$this->add(VanillaItems::ENDER_EYE(), CreativeCategory::ITEMS);
		}

		$this->stripHardenedGlassFromCreative();
	}

	/**
	 * Removes all previously added items from the creative menu.
	 * Note: Players who are already online when this is called will not see this change.
	 */
	public function clear() : void{
		$this->creative = [];
		$this->onContentChange();
	}

	/**
	 * @return Item[]
	 * @phpstan-return array<int, Item>
	 */
	public function getAll() : array{
		return array_map(fn(CreativeInventoryEntry $entry) => $entry->getItem(), $this->creative);
	}

	/**
	 * @return CreativeInventoryEntry[]
	 * @phpstan-return array<int, CreativeInventoryEntry>
	 */
	public function getAllEntries() : array{
		return $this->creative;
	}

	public function getItem(int $index) : ?Item{
		return $this->getEntry($index)?->getItem();
	}

	public function getEntry(int $index) : ?CreativeInventoryEntry{
		return $this->creative[$index] ?? null;
	}

	public function getItemIndex(Item $item) : int{
		foreach($this->creative as $i => $d){
			if($d->matchesItem($item)){
				return $i;
			}
		}

		return -1;
	}

	/**
	 * Adds an item to the creative menu.
	 * Note: Players who are already online when this is called will not see this change.
	 */
	public function add(Item $item, CreativeCategory $category = CreativeCategory::ITEMS, ?CreativeGroup $group = null) : void{
		$this->creative[] = new CreativeInventoryEntry($item, $category, $group);
		$this->onContentChange();
	}

	/**
	 * Removes an item from the creative menu.
	 * Note: Players who are already online when this is called will not see this change.
	 */
	public function remove(Item $item) : void{
		$index = $this->getItemIndex($item);
		if($index !== -1){
			unset($this->creative[$index]);
			$this->onContentChange();
		}
	}

	public function contains(Item $item) : bool{
		return $this->getItemIndex($item) !== -1;
	}

	/** @phpstan-return ObjectSet<\Closure() : void> */
	public function getContentChangedCallbacks() : ObjectSet{
		return $this->contentChangedCallbacks;
	}

	private function onContentChange() : void{
		foreach($this->contentChangedCallbacks as $callback){
			$callback();
		}
	}

	private function stripHardenedGlassFromCreative() : void{
		foreach($this->creative as $index => $entry){
			if(self::isHardenedGlassItem($entry->getItem())){
				unset($this->creative[$index]);
			}
		}

		$this->creative = array_values($this->creative);
	}

	private static function isHardenedGlassItem(Item $item) : bool{
		return match($item->getBlock()->getTypeId()){
			BlockTypeIds::HARDENED_GLASS,
			BlockTypeIds::HARDENED_GLASS_PANE,
			BlockTypeIds::STAINED_HARDENED_GLASS,
			BlockTypeIds::STAINED_HARDENED_GLASS_PANE => true,
			default => false
		};
	}
}
