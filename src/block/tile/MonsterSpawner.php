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
namespace pocketmine\block\tile;

use pocketmine\data\bedrock\LegacyEntityIdToStringIdMap;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use function max;
use function min;

/**
 * Tile спавнера мобов.
 *
 * Nukkit-MOT BlockEntitySpawner::getSpawnCompound() шлёт клиенту:
 *   id (String "MobSpawner"), EntityId (Int legacy), x/y/z (Int).
 * Современный Bedrock дополнительно требует SpawnData + DisplayEntity* для модели в клетке.
 */
class MonsterSpawner extends Spawnable{

	private const TAG_LEGACY_ENTITY_TYPE_ID = "EntityId"; //TAG_Int — legacy numeric id (Nukkit)
	private const TAG_ENTITY_TYPE_ID = "EntityIdentifier"; //TAG_String — string id (Bedrock 1.16+)
	private const TAG_SPAWN_DELAY = "Delay"; //TAG_Short
	private const TAG_SPAWN_POTENTIALS = "SpawnPotentials"; //TAG_List<TAG_Compound>
	private const TAG_SPAWN_DATA = "SpawnData"; //TAG_Compound
	private const TAG_MIN_SPAWN_DELAY = "MinSpawnDelay"; //TAG_Short
	private const TAG_MAX_SPAWN_DELAY = "MaxSpawnDelay"; //TAG_Short
	private const TAG_SPAWN_PER_ATTEMPT = "SpawnCount"; //TAG_Short
	private const TAG_MAX_NEARBY_ENTITIES = "MaxNearbyEntities"; //TAG_Short
	private const TAG_REQUIRED_PLAYER_RANGE = "RequiredPlayerRange"; //TAG_Short
	private const TAG_SPAWN_RANGE = "SpawnRange"; //TAG_Short
	private const TAG_MINIMUM_SPAWN_COUNT = "MinimumSpawnerCount"; //TAG_Short
	private const TAG_MAXIMUM_SPAWN_COUNT = "MaximumSpawnerCount"; //TAG_Short
	private const TAG_ENTITY_WIDTH = "DisplayEntityWidth"; //TAG_Float
	private const TAG_ENTITY_HEIGHT = "DisplayEntityHeight"; //TAG_Float
	private const TAG_ENTITY_SCALE = "DisplayEntityScale"; //TAG_Float

	private const SPAWN_DATA_TYPE_ID = "TypeId";
	private const SPAWN_DATA_ID = "id";
	private const SPAWN_DATA_IDENTIFIER = "identifier";

	private const DISPLAY_MAX_WIDTH = 0.53125;
	private const DISPLAY_MAX_HEIGHT = 0.45;

	public const DEFAULT_MIN_SPAWN_DELAY = 200;
	public const DEFAULT_MAX_SPAWN_DELAY = 800;

	public const DEFAULT_MAX_NEARBY_ENTITIES = 6;
	public const DEFAULT_SPAWN_RANGE = 4;
	public const DEFAULT_REQUIRED_PLAYER_RANGE = 16;

	public const DEFAULT_MINIMUM_SPAWN_COUNT = 1;
	public const DEFAULT_MAXIMUM_SPAWN_COUNT = 4;

	/** @var array<string, array{0: float, 1: float}> width, height */
	private const KNOWN_ENTITY_DIMENSIONS = [
		"minecraft:blaze" => [0.6, 1.8],
		"minecraft:cave_spider" => [0.7, 0.5],
		"minecraft:chicken" => [0.4, 0.7],
		"minecraft:cow" => [0.9, 1.4],
		"minecraft:creeper" => [0.6, 1.7],
		"minecraft:drowned" => [0.6, 1.9],
		"minecraft:ender_dragon" => [3.0, 3.0],
		"minecraft:enderman" => [0.6, 2.9],
		"minecraft:ghast" => [4.0, 4.0],
		"minecraft:guardian" => [0.85, 0.85],
		"minecraft:hoglin" => [1.3964844, 1.4],
		"minecraft:husk" => [0.6, 1.9],
		"minecraft:iron_golem" => [1.4, 2.7],
		"minecraft:magma_cube" => [0.52, 0.52],
		"minecraft:pig" => [0.9, 0.9],
		"minecraft:piglin" => [0.6, 1.9],
		"minecraft:pillager" => [0.6, 1.9],
		"minecraft:rabbit" => [0.4, 0.5],
		"minecraft:sheep" => [0.9, 1.3],
		"minecraft:silverfish" => [0.4, 0.3],
		"minecraft:skeleton" => [0.6, 1.99],
		"minecraft:slime" => [0.52, 0.52],
		"minecraft:spider" => [1.4, 0.9],
		"minecraft:stray" => [0.6, 1.99],
		"minecraft:strider" => [0.9, 1.7],
		"minecraft:vindicator" => [0.6, 1.95],
		"minecraft:witch" => [0.6, 1.95],
		"minecraft:wither_skeleton" => [0.7, 2.4],
		"minecraft:wolf" => [0.6, 0.85],
		"minecraft:zombie" => [0.6, 1.9],
		"minecraft:zombie_pigman" => [0.6, 1.9],
		"minecraft:zombie_villager" => [0.6, 1.9],
	];

	private string $entityTypeId = "";
	private int $legacyEntityTypeId = 0;
	private ?ListTag $spawnPotentials = null;
	private ?CompoundTag $spawnData = null;

	private float $displayEntityWidth = 0.875;
	private float $displayEntityHeight = 0.875;
	private float $displayEntityScale = 0.45;

	private int $spawnDelay = self::DEFAULT_MIN_SPAWN_DELAY;
	private int $minSpawnDelay = self::DEFAULT_MIN_SPAWN_DELAY;
	private int $maxSpawnDelay = self::DEFAULT_MAX_SPAWN_DELAY;
	private int $spawnPerAttempt = 1;
	private int $maxNearbyEntities = self::DEFAULT_MAX_NEARBY_ENTITIES;
	private int $spawnRange = self::DEFAULT_SPAWN_RANGE;
	private int $requiredPlayerRange = self::DEFAULT_REQUIRED_PLAYER_RANGE;
	private int $minimumSpawnCount = self::DEFAULT_MINIMUM_SPAWN_COUNT;
	private int $maximumSpawnCount = self::DEFAULT_MAXIMUM_SPAWN_COUNT;

	private bool $pendingClientSync = false;

	public function getEntityTypeId() : string{
		return $this->entityTypeId;
	}

	public function getLegacyEntityTypeId() : int{
		return $this->legacyEntityTypeId;
	}

	public function hasValidEntityType() : bool{
		return $this->resolveLegacyEntityId() > 0 || ($this->entityTypeId !== "" && $this->entityTypeId !== ":");
	}

	public function getSpawnDelay() : int{
		return $this->spawnDelay;
	}

	public function setSpawnDelay(int $delay) : void{
		$this->spawnDelay = max(0, $delay);
	}

	/**
	 * Nukkit BlockEntitySpawner::setSpawnEntityType(int entityId) + spawnToAll().
	 */
	public function setLegacyEntityTypeId(int $legacyEntityTypeId) : void{
		$this->legacyEntityTypeId = max(0, $legacyEntityTypeId);
		$this->entityTypeId = $this->legacyEntityTypeId > 0
			? (LegacyEntityIdToStringIdMap::getInstance()->legacyToString($this->legacyEntityTypeId) ?? "")
			: "";
		$this->rebuildSpawnData();
		$this->refreshDisplayMetrics();
		$this->clearSpawnCompoundCache();
		$this->pendingClientSync = true;
	}

	public function setEntityTypeId(string $entityTypeId) : void{
		if($entityTypeId === ":"){
			$entityTypeId = "";
		}
		$this->entityTypeId = $entityTypeId;
		$this->legacyEntityTypeId = $entityTypeId !== ""
			? (LegacyEntityIdToStringIdMap::getInstance()->stringToLegacy($entityTypeId) ?? 0)
			: 0;
		$this->rebuildSpawnData();
		$this->refreshDisplayMetrics();
		$this->clearSpawnCompoundCache();
		$this->pendingClientSync = true;
	}

	/**
	 * Nukkit BlockEntitySpawnable::spawnToAll() — только BlockActorDataPacket.
	 */
	public function syncToClients() : void{
		$this->clearSpawnCompoundCache();
		$world = $this->position->getWorld();
		$world->broadcastPacketToViewersByTypeConverter(
			$this->position,
			fn(TypeConverter $typeConverter) : array => [
				BlockActorDataPacket::create(
					BlockPosition::fromVector3($this->position),
					$this->getSerializedSpawnCompound($typeConverter)
				),
			]
		);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->entityTypeId = "";
		$this->legacyEntityTypeId = 0;

		if(($legacyIdTag = $nbt->getTag(self::TAG_LEGACY_ENTITY_TYPE_ID)) instanceof IntTag){
			$this->legacyEntityTypeId = $legacyIdTag->getValue();
			$this->entityTypeId = LegacyEntityIdToStringIdMap::getInstance()->legacyToString($this->legacyEntityTypeId) ?? "";
		}

		if(($idTag = $nbt->getTag(self::TAG_ENTITY_TYPE_ID)) instanceof StringTag){
			$id = $idTag->getValue();
			if($id !== "" && $id !== ":"){
				$this->entityTypeId = $id;
			}
		}

		$this->spawnData = $nbt->getCompoundTag(self::TAG_SPAWN_DATA);
		if($this->spawnData !== null){
			$spawnId = $this->extractEntityIdFromSpawnCompound($this->spawnData);
			if($spawnId !== ""){
				$this->entityTypeId = $spawnId;
			}
		}

		if($this->entityTypeId !== "" && $this->legacyEntityTypeId === 0){
			$this->legacyEntityTypeId = LegacyEntityIdToStringIdMap::getInstance()->stringToLegacy($this->entityTypeId) ?? 0;
		}

		$this->spawnPotentials = $nbt->getListTag(self::TAG_SPAWN_POTENTIALS);

		$this->spawnDelay = $nbt->getShort(self::TAG_SPAWN_DELAY, self::DEFAULT_MIN_SPAWN_DELAY);
		$this->minSpawnDelay = $nbt->getShort(self::TAG_MIN_SPAWN_DELAY, self::DEFAULT_MIN_SPAWN_DELAY);
		$this->maxSpawnDelay = $nbt->getShort(self::TAG_MAX_SPAWN_DELAY, self::DEFAULT_MAX_SPAWN_DELAY);
		$this->spawnPerAttempt = $nbt->getShort(self::TAG_SPAWN_PER_ATTEMPT, 1);
		$this->maxNearbyEntities = $nbt->getShort(self::TAG_MAX_NEARBY_ENTITIES, self::DEFAULT_MAX_NEARBY_ENTITIES);
		$this->requiredPlayerRange = $nbt->getShort(self::TAG_REQUIRED_PLAYER_RANGE, self::DEFAULT_REQUIRED_PLAYER_RANGE);
		$this->spawnRange = $nbt->getShort(self::TAG_SPAWN_RANGE, self::DEFAULT_SPAWN_RANGE);
		$this->minimumSpawnCount = $nbt->getShort(self::TAG_MINIMUM_SPAWN_COUNT, self::DEFAULT_MINIMUM_SPAWN_COUNT);
		$this->maximumSpawnCount = $nbt->getShort(self::TAG_MAXIMUM_SPAWN_COUNT, self::DEFAULT_MAXIMUM_SPAWN_COUNT);

		if($nbt->getTag(self::TAG_ENTITY_WIDTH) !== null){
			$this->displayEntityWidth = $nbt->getFloat(self::TAG_ENTITY_WIDTH, 0.875);
		}
		if($nbt->getTag(self::TAG_ENTITY_HEIGHT) !== null){
			$this->displayEntityHeight = $nbt->getFloat(self::TAG_ENTITY_HEIGHT, 0.875);
		}
		if($nbt->getTag(self::TAG_ENTITY_SCALE) !== null){
			$this->displayEntityScale = $nbt->getFloat(self::TAG_ENTITY_SCALE, 0.45);
		}

		if($this->hasValidEntityType()){
			$this->rebuildSpawnData();
			if($nbt->getTag(self::TAG_ENTITY_WIDTH) === null || $nbt->getTag(self::TAG_ENTITY_SCALE) === null){
				$this->refreshDisplayMetrics();
			}
			$this->pendingClientSync = true;
		}

		$this->clearSpawnCompoundCache();
	}

	public function pushClientSyncIfNeeded() : void{
		if(!$this->pendingClientSync){
			return;
		}
		$this->pendingClientSync = false;
		$this->syncToClients();
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$legacyId = $this->resolveLegacyEntityId();
		if($legacyId > 0){
			$nbt->setInt(self::TAG_LEGACY_ENTITY_TYPE_ID, $legacyId);
		}

		$stringId = $this->resolveStringEntityId();
		if($stringId !== ""){
			$nbt->setString(self::TAG_ENTITY_TYPE_ID, $stringId);
		}

		if($this->spawnData !== null){
			$nbt->setTag(self::TAG_SPAWN_DATA, clone $this->spawnData);
		}
		if($this->spawnPotentials !== null){
			$nbt->setTag(self::TAG_SPAWN_POTENTIALS, clone $this->spawnPotentials);
		}

		$nbt->setShort(self::TAG_SPAWN_DELAY, $this->spawnDelay);
		$nbt->setShort(self::TAG_MIN_SPAWN_DELAY, $this->minSpawnDelay);
		$nbt->setShort(self::TAG_MAX_SPAWN_DELAY, $this->maxSpawnDelay);
		$nbt->setShort(self::TAG_SPAWN_PER_ATTEMPT, $this->spawnPerAttempt);
		$nbt->setShort(self::TAG_MAX_NEARBY_ENTITIES, $this->maxNearbyEntities);
		$nbt->setShort(self::TAG_REQUIRED_PLAYER_RANGE, $this->requiredPlayerRange);
		$nbt->setShort(self::TAG_SPAWN_RANGE, $this->spawnRange);
		$nbt->setShort(self::TAG_MINIMUM_SPAWN_COUNT, $this->minimumSpawnCount);
		$nbt->setShort(self::TAG_MAXIMUM_SPAWN_COUNT, $this->maximumSpawnCount);

		$nbt->setFloat(self::TAG_ENTITY_WIDTH, $this->displayEntityWidth);
		$nbt->setFloat(self::TAG_ENTITY_HEIGHT, $this->displayEntityHeight);
		$nbt->setFloat(self::TAG_ENTITY_SCALE, $this->displayEntityScale);
	}

	/**
	 * NBT для BlockActorDataPacket / getSerializedSpawnCompound().
	 *
	 * Базовый Spawnable уже добавляет id="MobSpawner", x, y, z.
	 * Здесь — EntityId (Int), SpawnData, DisplayEntity* и Short-теги задержек.
	 */
	protected function addAdditionalSpawnData(CompoundTag $nbt, TypeConverter $typeConverter) : void{
		if(!$this->hasValidEntityType()){
			return;
		}

		$legacyId = $this->resolveLegacyEntityId();
		$stringId = $this->resolveStringEntityId();

		// Nukkit getSpawnCompound(): EntityId — IntTag (legacy numeric id), НЕ строка!
		if($legacyId > 0){
			$nbt->setInt(self::TAG_LEGACY_ENTITY_TYPE_ID, $legacyId);
		}

		if($stringId !== ""){
			$nbt->setString(self::TAG_ENTITY_TYPE_ID, $stringId);
			$nbt->setTag(self::TAG_SPAWN_DATA, $this->buildClientSpawnDataCompound($stringId));
		}

		$nbt->setShort(self::TAG_SPAWN_DELAY, $this->spawnDelay);
		$nbt->setShort(self::TAG_MIN_SPAWN_DELAY, $this->minSpawnDelay);
		$nbt->setShort(self::TAG_MAX_SPAWN_DELAY, $this->maxSpawnDelay);
		$nbt->setShort(self::TAG_SPAWN_PER_ATTEMPT, $this->spawnPerAttempt);
		$nbt->setShort(self::TAG_MAX_NEARBY_ENTITIES, $this->maxNearbyEntities);
		$nbt->setShort(self::TAG_REQUIRED_PLAYER_RANGE, $this->requiredPlayerRange);
		$nbt->setShort(self::TAG_SPAWN_RANGE, $this->spawnRange);
		$nbt->setShort(self::TAG_MINIMUM_SPAWN_COUNT, $this->minimumSpawnCount);
		$nbt->setShort(self::TAG_MAXIMUM_SPAWN_COUNT, $this->maximumSpawnCount);

		$nbt->setFloat(self::TAG_ENTITY_WIDTH, $this->displayEntityWidth);
		$nbt->setFloat(self::TAG_ENTITY_HEIGHT, $this->displayEntityHeight);
		$nbt->setFloat(self::TAG_ENTITY_SCALE, $this->displayEntityScale);
	}

	private function resolveLegacyEntityId() : int{
		if($this->legacyEntityTypeId > 0){
			return $this->legacyEntityTypeId;
		}
		if($this->entityTypeId !== "" && $this->entityTypeId !== ":"){
			return LegacyEntityIdToStringIdMap::getInstance()->stringToLegacy($this->entityTypeId) ?? 0;
		}
		return 0;
	}

	private function resolveStringEntityId() : string{
		if($this->entityTypeId !== "" && $this->entityTypeId !== ":"){
			return $this->entityTypeId;
		}
		$legacyId = $this->resolveLegacyEntityId();
		if($legacyId > 0){
			return LegacyEntityIdToStringIdMap::getInstance()->legacyToString($legacyId) ?? "";
		}
		return "";
	}

	private function rebuildSpawnData() : void{
		if(!$this->hasValidEntityType()){
			$this->spawnData = null;
			return;
		}
		$stringId = $this->resolveStringEntityId();
		$this->spawnData = $stringId !== "" ? $this->buildClientSpawnDataCompound($stringId) : null;
	}

	private function buildClientSpawnDataCompound(string $stringId) : CompoundTag{
		return CompoundTag::create()
			->setString(self::SPAWN_DATA_TYPE_ID, $stringId)
			->setString(self::SPAWN_DATA_ID, $stringId)
			->setString(self::SPAWN_DATA_IDENTIFIER, $stringId)
			->setShort("Weight", 1);
	}

	private function extractEntityIdFromSpawnCompound(CompoundTag $spawnData) : string{
		$typeId = $spawnData->getString(self::SPAWN_DATA_TYPE_ID, "");
		if($typeId !== "" && $typeId !== ":"){
			return $typeId;
		}

		$id = $spawnData->getString(self::SPAWN_DATA_ID, "");
		if($id !== "" && $id !== ":"){
			return $id;
		}

		$identifier = $spawnData->getString(self::SPAWN_DATA_IDENTIFIER, "");
		if($identifier !== "" && $identifier !== ":"){
			return $identifier;
		}

		$entityTag = $spawnData->getCompoundTag("entity");
		if($entityTag !== null){
			$nested = $entityTag->getString(self::SPAWN_DATA_ID, $entityTag->getString(self::SPAWN_DATA_IDENTIFIER, ""));
			if($nested !== "" && $nested !== ":"){
				return $nested;
			}
		}

		return "";
	}

	private function refreshDisplayMetrics() : void{
		$stringId = $this->resolveStringEntityId();
		if($stringId === ""){
			return;
		}

		[$width, $height] = self::KNOWN_ENTITY_DIMENSIONS[$stringId] ?? [0.6, 1.0];
		$scale = min(self::DISPLAY_MAX_WIDTH / $width, self::DISPLAY_MAX_HEIGHT / $height, 1.0);
		$this->displayEntityScale = $scale;
		$this->displayEntityWidth = $width * $scale;
		$this->displayEntityHeight = $height * $scale;
	}
}
