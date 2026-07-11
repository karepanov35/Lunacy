<?php

declare(strict_types=1);
namespace pocketmine\world\io;

use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\LoadedChunkData;
use function igbinary_unserialize;

final class ChunkIoResult{
	private function __construct(
		private bool $success,
		private ?string $payload,
		private ?string $error
	){}

	public static function loaded(string $serializedChunkData) : self{
		return new self(true, $serializedChunkData, null);
	}

	public static function missing() : self{
		return new self(true, null, null);
	}

	public static function failed(string $error) : self{
		return new self(false, null, $error);
	}

	public function isSuccess() : bool{
		return $this->success;
	}

	public function getError() : ?string{
		return $this->error;
	}

	public function hasChunkData() : bool{
		return $this->payload !== null;
	}

	public function toLoadedChunkData() : ?LoadedChunkData{
		if($this->payload === null){
			return null;
		}
		/** @var array{data: string, upgraded: bool, fixerFlags: int} $decoded */
		$decoded = igbinary_unserialize($this->payload) ?? throw new \InvalidArgumentException("Invalid chunk IO payload");
		/** @var ChunkData $chunkData */
		$chunkData = igbinary_unserialize($decoded['data']) ?? throw new \InvalidArgumentException("Invalid chunk data payload");
		return new LoadedChunkData($chunkData, $decoded['upgraded'], $decoded['fixerFlags']);
	}
}
