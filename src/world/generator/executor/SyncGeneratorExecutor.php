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
namespace pocketmine\world\generator\executor;

use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\PopulationUtils;

final class SyncGeneratorExecutor implements GeneratorExecutor{

	private readonly Generator $generator;
	private readonly int $worldMinY;
	private readonly int $worldMaxY;

	public function __construct(
		GeneratorExecutorSetupParameters $setupParameters
	){
		$this->generator = $setupParameters->createGenerator();
		$this->worldMinY = $setupParameters->worldMinY;
		$this->worldMaxY = $setupParameters->worldMaxY;
	}

	public function populate(int $chunkX, int $chunkZ, ?Chunk $centerChunk, array $adjacentChunks, \Closure $onCompletion) : void{
		[$centerChunk, $adjacentChunks] = PopulationUtils::populateChunkWithAdjacents(
			$this->worldMinY,
			$this->worldMaxY,
			$this->generator,
			$chunkX,
			$chunkZ,
			$centerChunk,
			$adjacentChunks
		);

		$onCompletion($centerChunk, $adjacentChunks);
	}

	public function shutdown() : void{
		//NOOP
	}
}
