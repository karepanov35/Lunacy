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

use pocketmine\scheduler\AsyncTask;

class AsyncGeneratorRegisterTask extends AsyncTask{

	public function __construct(
		private readonly GeneratorExecutorSetupParameters $setupParameters,
		private readonly int $contextId
	){}

	public function onRun() : void{
		$setupParameters = $this->setupParameters;
		$generator = $setupParameters->createGenerator();
		ThreadLocalGeneratorContext::register(new ThreadLocalGeneratorContext($generator, $setupParameters->worldMinY, $setupParameters->worldMaxY), $this->contextId);
	}
}
