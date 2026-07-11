<?php

declare(strict_types=1);
namespace pocketmine\world\io;

use pocketmine\scheduler\AsyncTask;
use pocketmine\world\format\io\ChunkData;
use pocketmine\world\format\io\WritableWorldProvider;
use function igbinary_unserialize;

final class ChunkSaveTask extends AsyncTask{
	private const TLS_KEY_ON_SUCCESS = "onSuccess";
	private const TLS_KEY_ON_ERROR = "onError";

	protected string $providerClass;
	protected string $worldPath;
	protected int $chunkX;
	protected int $chunkZ;
	protected int $generation;
	protected int $dirtyFlags;
	protected string $chunkData;

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
		int $dirtyFlags,
		string $serializedChunkData,
		\Closure $onSuccess,
		\Closure $onError
	){
		$this->providerClass = $providerClass;
		$this->worldPath = $worldPath;
		$this->chunkX = $chunkX;
		$this->chunkZ = $chunkZ;
		$this->generation = $generation;
		$this->dirtyFlags = $dirtyFlags;
		$this->chunkData = $serializedChunkData;

		$this->storeLocal(self::TLS_KEY_ON_SUCCESS, $onSuccess);
		$this->storeLocal(self::TLS_KEY_ON_ERROR, $onError);
	}

	public function onRun() : void{
		/** @var class-string<WritableWorldProvider> $providerClass */
		$providerClass = $this->providerClass;
		/** @var WritableWorldProvider $provider */
		$provider = new $providerClass($this->worldPath, new NoOpThreadSafeLogger());

		try{
			/** @var ChunkData $chunkData */
			$chunkData = igbinary_unserialize($this->chunkData) ?? throw new \InvalidArgumentException("Invalid chunk data");
			$provider->saveChunk($this->chunkX, $this->chunkZ, $chunkData, $this->dirtyFlags);
			$this->setResult(true);
		}catch(\Throwable){
			$this->setResult(false);
		}finally{
			$provider->close();
		}
	}

	public function onCompletion() : void{
		/** @var \Closure(ChunkIoResult) : void $onSuccess */
		$onSuccess = $this->fetchLocal(self::TLS_KEY_ON_SUCCESS);
		/** @var \Closure(ChunkIoResult) : void $onError */
		$onError = $this->fetchLocal(self::TLS_KEY_ON_ERROR);

		if($this->getResult() === true){
			$onSuccess(ChunkIoResult::missing());
		}else{
			$onError(ChunkIoResult::failed("save"));
		}
	}
}
