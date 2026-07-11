<?php

declare(strict_types=1);
namespace pocketmine\world\io;

use pocketmine\scheduler\AsyncTask;
use pocketmine\world\format\io\exception\CorruptedChunkException;
use pocketmine\world\format\io\WritableWorldProvider;
use function igbinary_serialize;

final class ChunkLoadTask extends AsyncTask{
	private const TLS_KEY_ON_SUCCESS = "onSuccess";
	private const TLS_KEY_ON_ERROR = "onError";

	protected string $providerClass;
	protected string $worldPath;
	protected int $chunkX;
	protected int $chunkZ;
	protected int $generation;
	protected float $priority;

	/**
	 * @phpstan-param class-string<WritableWorldProvider> $providerClass
	 * @phpstan-param \Closure(ChunkIoResult) : void $onSuccess
	 * @phpstan-param \Closure(ChunkIoResult) : void $onError
	 */
	public function __construct(
		string $providerClass,
		string $worldPath,
		int $chunkX,
		int $chunkZ,
		int $generation,
		float $priority,
		\Closure $onSuccess,
		\Closure $onError
	){
		$this->providerClass = $providerClass;
		$this->worldPath = $worldPath;
		$this->chunkX = $chunkX;
		$this->chunkZ = $chunkZ;
		$this->generation = $generation;
		$this->priority = $priority;

		$this->storeLocal(self::TLS_KEY_ON_SUCCESS, $onSuccess);
		$this->storeLocal(self::TLS_KEY_ON_ERROR, $onError);
	}

	public function onRun() : void{
		/** @var class-string<WritableWorldProvider> $providerClass */
		$providerClass = $this->providerClass;
		/** @var WritableWorldProvider $provider */
		$provider = new $providerClass($this->worldPath, new NoOpThreadSafeLogger());

		try{
			$loadedChunkData = $provider->loadChunk($this->chunkX, $this->chunkZ);
		}catch(CorruptedChunkException){
			$this->setResult(igbinary_serialize(['status' => 'corrupted']) ?: "");
			return;
		}finally{
			$provider->close();
		}

		if($loadedChunkData === null){
			$this->setResult(igbinary_serialize(['status' => 'missing']) ?: "");
			return;
		}

		$chunkData = $loadedChunkData->getData();
		$payload = igbinary_serialize([
			'data' => igbinary_serialize($chunkData),
			'upgraded' => $loadedChunkData->isUpgraded(),
			'fixerFlags' => $loadedChunkData->getFixerFlags()
		]);
		$this->setResult(igbinary_serialize(['status' => 'loaded', 'payload' => $payload]) ?: "");
	}

	public function onCompletion() : void{
		/** @var \Closure(ChunkIoResult) : void $onSuccess */
		$onSuccess = $this->fetchLocal(self::TLS_KEY_ON_SUCCESS);
		/** @var \Closure(ChunkIoResult) : void $onError */
		$onError = $this->fetchLocal(self::TLS_KEY_ON_ERROR);

		/** @var array{status: string, payload?: string} $decoded */
		$decoded = igbinary_unserialize($this->getResult()) ?? ['status' => 'corrupted'];

		match($decoded['status']){
			'loaded' => $onSuccess(ChunkIoResult::loaded($decoded['payload'] ?? throw new \InvalidArgumentException("Missing payload"))),
			'missing' => $onSuccess(ChunkIoResult::missing()),
			default => $onError(ChunkIoResult::failed($decoded['status']))
		};
	}
}
