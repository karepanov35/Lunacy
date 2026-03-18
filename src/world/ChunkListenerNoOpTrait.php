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
namespace pocketmine\world;

use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;

/**
 * This trait implements no-op default methods for chunk listeners.
 * @see ChunkListener
 */
trait ChunkListenerNoOpTrait/* implements ChunkListener*/{

	public function onChunkChanged(int $chunkX, int $chunkZ, Chunk $chunk) : void{
		//NOOP
	}

	public function onChunkLoaded(int $chunkX, int $chunkZ, Chunk $chunk) : void{
		//NOOP
	}

	public function onChunkUnloaded(int $chunkX, int $chunkZ, Chunk $chunk) : void{
		//NOOP
	}

	public function onChunkPopulated(int $chunkX, int $chunkZ, Chunk $chunk) : void{
		//NOOP
	}

	public function onBlockChanged(Vector3 $block) : void{
		//NOOP
	}
}
