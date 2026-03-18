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
namespace pocketmine\scheduler;

use pmmp\thread\Thread as NativeThread;
use pocketmine\MemoryDump;
use Symfony\Component\Filesystem\Path;
use function assert;

/**
 * Task used to dump memory from AsyncWorkers
 */
class DumpWorkerMemoryTask extends AsyncTask{
	public function __construct(
		private string $outputFolder,
		private int $maxNesting,
		private int $maxStringSize
	){}

	public function onRun() : void{
		$worker = NativeThread::getCurrentThread();
		assert($worker instanceof AsyncWorker);
		MemoryDump::dumpMemory(
			$worker,
			Path::join($this->outputFolder, "AsyncWorker#" . $worker->getAsyncWorkerId()),
			$this->maxNesting,
			$this->maxStringSize,
			new \PrefixedLogger($worker->getLogger(), "Memory Dump")
		);
	}
}
