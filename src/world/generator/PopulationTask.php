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
namespace pocketmine\world\generator;

use pocketmine\scheduler\AsyncTask;
use pocketmine\timings\Timings;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\FastChunkSerializer;
use pocketmine\world\generator\executor\ThreadLocalGeneratorContext;
use function array_map;
use function igbinary_serialize;
use function igbinary_unserialize;

/**
 * @internal
 *
 * TODO: this should be moved to the executor namespace, but plugins have unfortunately used it directly due to the
 * difficulty of regenerating chunks. This should be addressed in the future.
 * For the remainder of PM5, we can't relocate this class.
 *
 * @phpstan-type OnCompletion \Closure(Chunk $centerChunk, array<int, Chunk> $adjacentChunks) : void
 */
class PopulationTask extends AsyncTask{
	private const TLS_KEY_ON_COMPLETION = "onCompletion";

	private static ?TimingsHandler $timingsDeserialize = null;
	private static ?TimingsHandler $timingsPopulate = null;
	private static ?TimingsHandler $timingsSerialize = null;

	private ?string $chunk;

	private string $adjacentChunks;

	/**
	 * @param Chunk[]|null[] $adjacentChunks
	 * @phpstan-param array<int, Chunk|null> $adjacentChunks
	 * @phpstan-param OnCompletion $onCompletion
	 */
	public function __construct(
		private int $worldId,
		private int $chunkX,
		private int $chunkZ,
		?Chunk $chunk,
		array $adjacentChunks,
		\Closure $onCompletion
	){
		$this->chunk = $chunk !== null ? FastChunkSerializer::serializeTerrain($chunk) : null;

		$this->adjacentChunks = igbinary_serialize(array_map(
			fn(?Chunk $c) => $c !== null ? FastChunkSerializer::serializeTerrain($c) : null,
			$adjacentChunks
		)) ?? throw new AssumptionFailedError("igbinary_serialize() returned null");

		$this->storeLocal(self::TLS_KEY_ON_COMPLETION, $onCompletion);
	}

	/**
	 * @return array{TimingsHandler, TimingsHandler, TimingsHandler}
	 */
	private static function getPhaseTimings(AsyncTask $task) : array{
		if(self::$timingsDeserialize === null){
			$parent = Timings::getAsyncTaskRunTimings($task);
			self::$timingsDeserialize = new TimingsHandler("AsyncTask - PopulationTask - Run - Deserialize", $parent);
			self::$timingsPopulate = new TimingsHandler("AsyncTask - PopulationTask - Run - Populate", $parent);
			self::$timingsSerialize = new TimingsHandler("AsyncTask - PopulationTask - Run - Serialize", $parent);
		}

		return [self::$timingsDeserialize, self::$timingsPopulate, self::$timingsSerialize];
	}

	public function onRun() : void{
		$context = ThreadLocalGeneratorContext::fetch($this->worldId);
		if($context === null){
			throw new AssumptionFailedError("Generator context should have been initialized before any PopulationTask execution");
		}

		[$timingDeserialize, $timingPopulate, $timingSerialize] = self::getPhaseTimings($this);

		$timingDeserialize->startTiming();
		$chunk = $this->chunk !== null ? FastChunkSerializer::deserializeTerrain($this->chunk) : null;

		/**
		 * @var string[] $serialChunks
		 * @phpstan-var array<int, string|null> $serialChunks
		 */
		$serialChunks = igbinary_unserialize($this->adjacentChunks);
		$chunks = array_map(
			function(?string $serialized) : ?Chunk{
				if($serialized === null){
					return null;
				}
				$chunk = FastChunkSerializer::deserializeTerrain($serialized);
				$chunk->clearTerrainDirtyFlags(); //this allows us to avoid sending existing chunks back to the main thread if they haven't changed during generation
				return $chunk;
			},
			$serialChunks
		);
		$timingDeserialize->stopTiming();

		$timingPopulate->startTiming();
		[$chunk, $chunks] = PopulationUtils::populateChunkWithAdjacents(
			$context->getWorldMinY(),
			$context->getWorldMaxY(),
			$context->getGenerator(),
			$this->chunkX,
			$this->chunkZ,
			$chunk,
			$chunks
		);
		$timingPopulate->stopTiming();

		$timingSerialize->startTiming();
		$this->chunk = FastChunkSerializer::serializeTerrain($chunk);

		$serialChunks = [];
		foreach($chunks as $relativeChunkHash => $c){
			$serialChunks[$relativeChunkHash] = $c->isTerrainDirty() ? FastChunkSerializer::serializeTerrain($c) : null;
		}
		$this->adjacentChunks = igbinary_serialize($serialChunks) ?? throw new AssumptionFailedError("igbinary_serialize() returned null");
		$timingSerialize->stopTiming();
	}

	public function onCompletion() : void{
		/**
		 * @var \Closure $onCompletion
		 * @phpstan-var OnCompletion $onCompletion
		 */
		$onCompletion = $this->fetchLocal(self::TLS_KEY_ON_COMPLETION);

		$chunk = $this->chunk !== null ?
			FastChunkSerializer::deserializeTerrain($this->chunk) :
			throw new AssumptionFailedError("Center chunk should never be null");

		/**
		 * @var string[]|null[] $serialAdjacentChunks
		 * @phpstan-var array<int, string|null> $serialAdjacentChunks
		 */
		$serialAdjacentChunks = igbinary_unserialize($this->adjacentChunks);
		$adjacentChunks = [];
		foreach($serialAdjacentChunks as $relativeChunkHash => $c){
			if($c !== null){
				$adjacentChunks[$relativeChunkHash] = FastChunkSerializer::deserializeTerrain($c);
			}
		}

		$onCompletion($chunk, $adjacentChunks);
	}
}
