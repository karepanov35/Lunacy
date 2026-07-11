<?php

declare(strict_types=1);
namespace pocketmine\world\io;

use pocketmine\world\World;

final class WorldChunkIoQueue{
	/** @var array<int, int> */
	private array $loadGenerations = [];
	/** @var array<int, int> */
	private array $dispatchedLoads = [];
	/** @var array<int, int> */
	private array $saveGenerations = [];
	/** @var array<int, int> */
	private array $dispatchedSaves = [];

	public function reserveLoadGeneration(int $chunkX, int $chunkZ) : int{
		$hash = World::chunkHash($chunkX, $chunkZ);
		$generation = ($this->loadGenerations[$hash] ?? 0) + 1;
		$this->loadGenerations[$hash] = $generation;
		return $generation;
	}

	public function markLoadDispatched(int $chunkHash, int $generation) : void{
		$this->dispatchedLoads[$chunkHash] = $generation;
	}

	public function completeLoad(int $chunkHash, int $generation) : bool{
		if(($this->dispatchedLoads[$chunkHash] ?? null) !== $generation){
			return false;
		}
		if(($this->loadGenerations[$chunkHash] ?? null) !== $generation){
			return false;
		}
		unset($this->dispatchedLoads[$chunkHash], $this->loadGenerations[$chunkHash]);
		return true;
	}

	public function cancelAllLoads() : void{
		$this->loadGenerations = [];
		$this->dispatchedLoads = [];
	}

	public function reserveSaveGeneration(int $chunkX, int $chunkZ) : int{
		$hash = World::chunkHash($chunkX, $chunkZ);
		$generation = ($this->saveGenerations[$hash] ?? 0) + 1;
		$this->saveGenerations[$hash] = $generation;
		return $generation;
	}

	public function markSaveDispatched(int $chunkHash, int $generation) : void{
		$this->dispatchedSaves[$chunkHash] = $generation;
	}

	public function completeSave(int $chunkHash, int $generation) : bool{
		if(($this->dispatchedSaves[$chunkHash] ?? null) !== $generation){
			return false;
		}
		if(($this->saveGenerations[$chunkHash] ?? null) !== $generation){
			return false;
		}
		unset($this->dispatchedSaves[$chunkHash], $this->saveGenerations[$chunkHash]);
		return true;
	}

	public function hasPendingSaves() : bool{
		return $this->saveGenerations !== [] || $this->dispatchedSaves !== [];
	}

	public function cancelAllSaves() : void{
		$this->saveGenerations = [];
		$this->dispatchedSaves = [];
	}
}
