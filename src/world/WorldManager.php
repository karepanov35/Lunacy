<?php


/*
 *
 *
 *тЦТтЦИтЦСтЦСтЦС тЦТтЦИтЦСтЦТтЦИ тЦТтЦИтЦДтЦСтЦТтЦИ тЦСтЦИтЦАтЦАтЦИ тЦТтЦИтЦАтЦАтЦИ тЦТтЦИтЦСтЦСтЦТтЦИ
 *тЦТтЦИтЦСтЦСтЦС тЦТтЦИтЦСтЦТтЦИ тЦТтЦИтЦТтЦИтЦТтЦИ тЦТтЦИтЦДтЦДтЦИ тЦТтЦИтЦСтЦСтЦС тЦТтЦИтЦДтЦДтЦДтЦИ
 *тЦТтЦИтЦДтЦДтЦИ тЦСтЦАтЦДтЦДтЦА тЦТтЦИтЦСтЦСтЦАтЦИ тЦТтЦИтЦСтЦТтЦИ тЦТтЦИтЦДтЦДтЦИ тЦСтЦСтЦТтЦИтЦСтЦС
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

use pocketmine\entity\Entity;
use pocketmine\event\world\WorldInitEvent;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\player\ChunkSelector;
use pocketmine\Server;
use pocketmine\utils\Terminal;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\exception\CorruptedWorldException;
use pocketmine\world\format\io\exception\UnsupportedWorldFormatException;
use pocketmine\world\format\io\FormatConverter;
use pocketmine\world\format\io\WorldProviderManager;
use pocketmine\world\format\io\WritableWorldProvider;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use Symfony\Component\Filesystem\Path;
use function array_keys;
use function array_shift;
use function assert;
use function count;
use function floor;
use function implode;
use function iterator_to_array;
use function max;
use function microtime;
use function round;
use function sprintf;
use function strlen;
use function strval;
use function trim;

class WorldManager{
	public const TICKS_PER_AUTOSAVE = 300 * Server::TARGET_TICKS_PER_SECOND;

	/**
	 * @var World[]
	 * @phpstan-var array<int, World>
	 */
	private array $worlds = [];
	private ?World $defaultWorld = null;

	private bool $autoSave = true;
	private int $autoSaveTicks = self::TICKS_PER_AUTOSAVE;
	private int $autoSaveTicker = 0;

	/** @var array<string, array{done: int, total: int}> */
	private array $spawnGenerationWorldProgress = [];
	private int $spawnGenerationTotal = 0;
	private int $spawnGenerationDone = 0;
	private int $spawnGenerationLastProgress = -1;
	private bool $spawnGenerationInProgress = false;
	private bool $spawnGenerationFinished = false;
	private bool $progressLineVisible = false;

	private static ?self $consoleProgressOwner = null;

	public function __construct(
		private Server $server,
		private string $dataPath,
		private WorldProviderManager $providerManager
	){}

	public function getProviderManager() : WorldProviderManager{
		return $this->providerManager;
	}

	/**
	 * @return World[]
	 * @phpstan-return array<int, World>
	 */
	public function getWorlds() : array{
		return $this->worlds;
	}

	public function getDefaultWorld() : ?World{
		return $this->defaultWorld;
	}

	/**
	 * Sets the default world to a different world
	 * This won't change the level-name property,
	 * it only affects the server on runtime
	 */
	public function setDefaultWorld(?World $world) : void{
		if($world === null || ($this->isWorldLoaded($world->getFolderName()) && $world !== $this->defaultWorld)){
			$this->defaultWorld = $world;
		}
	}

	public function isWorldLoaded(string $name) : bool{
		return $this->getWorldByName($name) instanceof World;
	}

	public function getWorld(int $worldId) : ?World{
		return $this->worlds[$worldId] ?? null;
	}

	/**
	 * NOTE: This matches worlds based on the FOLDER name, NOT the display name.
	 */
	public function getWorldByName(string $name) : ?World{
		foreach($this->worlds as $world){
			if($world->getFolderName() === $name){
				return $world;
			}
		}

		return null;
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function unloadWorld(World $world, bool $forceUnload = false) : bool{
		if($world === $this->getDefaultWorld() && !$forceUnload){
			throw new \InvalidArgumentException("The default world cannot be unloaded while running, please switch worlds.");
		}
		if($world->isDoingTick()){
			throw new \InvalidArgumentException("Cannot unload a world during world tick");
		}

		$ev = new WorldUnloadEvent($world);
		$ev->call();

		if(!$forceUnload && $ev->isCancelled()){
			return false;
		}

		$this->server->getLogger()->info($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_unloading($world->getDisplayName())));
		if(count($world->getPlayers()) !== 0){
			try{
				$safeSpawn = $this->defaultWorld !== null && $this->defaultWorld !== $world ? $this->defaultWorld->getSafeSpawn() : null;
			}catch(WorldException $e){
				$safeSpawn = null;
			}
			foreach($world->getPlayers() as $player){
				if($safeSpawn === null){
					$player->disconnect("Forced default world unload");
				}else{
					$player->teleport($safeSpawn);
				}
			}
		}

		if($world === $this->defaultWorld){
			$this->defaultWorld = null;
		}
		unset($this->worlds[$world->getId()]);

		$world->onUnload();
		return true;
	}

	/**
	 * Loads a world from the data directory
	 *
	 * @param bool $autoUpgrade Converts worlds to the default format if the world's format is not writable / deprecated
	 *
	 * @throws WorldException
	 */
	public function loadWorld(string $name, bool $autoUpgrade = false) : bool{
		if(trim($name) === ""){
			throw new \InvalidArgumentException("Invalid empty world name");
		}
		if($this->isWorldLoaded($name)){
			return true;
		}elseif(!$this->isWorldGenerated($name)){
			return false;
		}

		$path = $this->getWorldPath($name);

		$providers = $this->providerManager->getMatchingProviders($path);
		if(count($providers) === 0){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_unknownFormat()
			)));
			return false;
		}
		if(count($providers) > 1){
			// Bedrock ╨╝╨╕╤А╤Л тАФ LevelDB; ╨╗╨╕╤И╨╜╤П╤П ╨┐╨░╨┐╨║╨░ region/ (╨╛╤Б╤В╨░╤В╨╛╨║ Java/╤И╨░╨▒╨╗╨╛╨╜╨░) ╨╖╨░╤Б╤В╨░╨▓╨╗╤П╨╡╤В ╤Б╨╛╨▓╨┐╨░╤Б╤В╤М ╨╕ ╤Б PMAnvil
			if(isset($providers["leveldb"])){
				$this->server->getLogger()->notice("╨Ь╨╕╤А ┬л{$name}┬╗: ╨╜╨╡╤Б╨║╨╛╨╗╤М╨║╨╛ ╤Д╨╛╤А╨╝╨░╤В╨╛╨▓ (" . implode(", ", array_keys($providers)) . ") тАФ ╨╖╨░╨│╤А╤Г╨╢╨░╨╡╤В╤Б╤П ╨║╨░╨║ leveldb (Bedrock). ╨г╨┤╨░╨╗╨╕ ╨╗╨╕╤И╨╜╤О╤О ╨┐╨░╨┐╨║╤Г region/ ╨▓ worlds/{$name}/ ╨╡╤Б╨╗╨╕ ╨╝╨╕╤А ╤В╨╛╨╗╤М╨║╨╛ Bedrock.");
				$providers = ["leveldb" => $providers["leveldb"]];
			}else{
				$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
					$name,
					KnownTranslationFactory::pocketmine_level_ambiguousFormat(implode(", ", array_keys($providers)))
				)));
				return false;
			}
		}
		$providerClass = array_shift($providers);

		try{
			$provider = $providerClass->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"));
		}catch(CorruptedWorldException $e){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_corrupted($e->getMessage())
			)));
			return false;
		}catch(UnsupportedWorldFormatException $e){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_unsupportedFormat($e->getMessage())
			)));
			return false;
		}

		$generatorEntry = GeneratorManager::getInstance()->getGenerator($provider->getWorldData()->getGenerator());
		if($generatorEntry === null){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_unknownGenerator($provider->getWorldData()->getGenerator())
			)));
			return false;
		}
		try{
			$generatorEntry->validateGeneratorOptions($provider->getWorldData()->getGeneratorOptions());
		}catch(InvalidGeneratorOptionsException $e){
			$this->server->getLogger()->error($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_loadError(
				$name,
				KnownTranslationFactory::pocketmine_level_invalidGeneratorOptions(
					$provider->getWorldData()->getGeneratorOptions(),
					$provider->getWorldData()->getGenerator(),
					$e->getMessage()
				)
			)));
			return false;
		}
		if(!($provider instanceof WritableWorldProvider)){
			if(!$autoUpgrade){
				throw new UnsupportedWorldFormatException("World \"$name\" is in an unsupported format and needs to be upgraded");
			}
			$this->server->getLogger()->notice($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_conversion_start($name)));

			$providerClass = $this->providerManager->getDefault();
			$converter = new FormatConverter($provider, $providerClass, Path::join($this->server->getDataPath(), "backups", "worlds"), $this->server->getLogger());
			$converter->execute();
			$provider = $providerClass->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name"));

			$this->server->getLogger()->notice($this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_conversion_finish($name, $converter->getBackupPath())));
		}

		$world = new World($this->server, $name, $provider, $this->server->getAsyncPool());

		$this->worlds[$world->getId()] = $world;
		$world->setAutoSave($this->autoSave);

		(new WorldLoadEvent($world))->call();

		return true;
	}

	/**
	 * Generates a new world if it does not exist
	 *
	 * @throws \InvalidArgumentException
	 */
	public function generateWorld(string $name, WorldCreationOptions $options, bool $backgroundGeneration = true) : bool{
		if(trim($name) === "" || $this->isWorldGenerated($name)){
			return false;
		}

		$providerEntry = $this->providerManager->getDefault();

		$path = $this->getWorldPath($name);
		$providerEntry->generate($path, $name, $options);

		$world = new World($this->server, $name, $providerEntry->fromPath($path, new \PrefixedLogger($this->server->getLogger(), "World Provider: $name")), $this->server->getAsyncPool());
		$this->worlds[$world->getId()] = $world;

		$world->setAutoSave($this->autoSave);

		(new WorldInitEvent($world))->call();

		(new WorldLoadEvent($world))->call();

		if($backgroundGeneration){
			$spawnLocation = $world->getSpawnLocation();
			$centerX = $spawnLocation->getFloorX() >> Chunk::COORD_BIT_SIZE;
			$centerZ = $spawnLocation->getFloorZ() >> Chunk::COORD_BIT_SIZE;

			$selected = iterator_to_array((new ChunkSelector())->selectChunks(8, $centerX, $centerZ), preserve_keys: false);
			$done = 0;
			$total = count($selected);

			$this->spawnGenerationWorldProgress[$name] = ['done' => 0, 'total' => $total];
			$this->spawnGenerationTotal += $total;
			$this->spawnGenerationInProgress = true;
			$this->spawnGenerationFinished = false;
			self::$consoleProgressOwner = $this;

			foreach($selected as $index){
				World::getXZ($index, $chunkX, $chunkZ);
				$world->requestChunkPopulation($chunkX, $chunkZ, null)->onCompletion(
					static function() use ($world, $name, &$done, $total) : void{
						$world->getServer()->getWorldManager()->onSpawnChunkGenerated($name, ++$done, $total);
					},
					static function() use ($world, $name, &$done, $total) : void{
						$world->getServer()->getWorldManager()->onSpawnChunkGenerated($name, ++$done, $total);
					});
			}
		}

		return true;
	}

	private function getWorldPath(string $name) : string{
		return Path::join($this->dataPath, $name) . "/"; //TODO: check if we still need the trailing dirsep (I'm a little scared to remove it)
	}

	public function isWorldGenerated(string $name) : bool{
		if(trim($name) === ""){
			return false;
		}
		$path = $this->getWorldPath($name);
		if(!($this->getWorldByName($name) instanceof World)){
			return count($this->providerManager->getMatchingProviders($path)) > 0;
		}

		return true;
	}

	/**
	 * Searches all worlds for the entity with the specified ID.
	 * Useful for tracking entities across multiple worlds without needing strong references.
	 */
	public function findEntity(int $entityId) : ?Entity{
		foreach($this->worlds as $world){
			assert($world->isLoaded());
			if(($entity = $world->getEntity($entityId)) instanceof Entity){
				return $entity;
			}
		}

		return null;
	}

	public function tick(int $currentTick) : void{
		foreach($this->worlds as $k => $world){
			if(!isset($this->worlds[$k])){
				// World unloaded during the tick of a world earlier in this loop, perhaps by plugin
				continue;
			}

			$worldTime = microtime(true);
			$world->doTick($currentTick);
			$tickMs = (microtime(true) - $worldTime) * 1000;
			$world->tickRateTime = $tickMs;
			if($tickMs >= Server::TARGET_SECONDS_PER_TICK * 1000){
				$world->getLogger()->debug(sprintf("Tick took too long: %gms (%g ticks)", $tickMs, round($tickMs / (Server::TARGET_SECONDS_PER_TICK * 1000), 2)));
			}
		}

		if($this->autoSave && ++$this->autoSaveTicker >= $this->autoSaveTicks){
			$this->autoSaveTicker = 0;
			$this->server->getLogger()->debug("[Auto Save] Saving worlds...");
			$start = microtime(true);
			$this->doAutoSave();
			$time = microtime(true) - $start;
			$this->server->getLogger()->debug("[Auto Save] Save completed in " . ($time >= 1 ? round($time, 3) . "s" : round($time * 1000) . "ms"));
		}
	}

	public function getAutoSave() : bool{
		return $this->autoSave;
	}

	public function setAutoSave(bool $value) : void{
		$this->autoSave = $value;
		foreach($this->worlds as $world){
			$world->setAutoSave($this->autoSave);
		}
	}

	/**
	 * Returns the period in ticks after which loaded worlds will be automatically saved to disk.
	 */
	public function getAutoSaveInterval() : int{
		return $this->autoSaveTicks;
	}

	public function setAutoSaveInterval(int $autoSaveTicks) : void{
		if($autoSaveTicks <= 0){
			throw new \InvalidArgumentException("Autosave ticks must be positive");
		}
		$this->autoSaveTicks = $autoSaveTicks;
	}

	private function doAutoSave() : void{
		foreach($this->worlds as $world){
			foreach($world->getPlayers() as $player){
				if($player->spawned){
					$player->save();
				}
			}
			$world->save(false);
		}
	}

	public function isSpawnGenerationInProgress() : bool{
		return $this->spawnGenerationInProgress;
	}

	public function onSpawnChunkGenerated(string $worldName, int $done, int $total) : void{
		$this->spawnGenerationWorldProgress[$worldName] = ['done' => $done, 'total' => $total];
		$this->spawnGenerationDone++;

		$progress = $this->spawnGenerationTotal > 0
			? (int) floor(($this->spawnGenerationDone / $this->spawnGenerationTotal) * 100)
			: 0;

		if($progress !== $this->spawnGenerationLastProgress){
			$this->spawnGenerationLastProgress = $progress;
			$this->printSpawnGenerationProgress();
		}

		if($this->spawnGenerationDone >= $this->spawnGenerationTotal && $this->spawnGenerationTotal > 0 && !$this->spawnGenerationFinished){
			$this->finishSpawnGeneration();
		}
	}

	private function printSpawnGenerationProgress() : void{
		$progress = $this->spawnGenerationTotal > 0
			? (int) floor(($this->spawnGenerationDone / $this->spawnGenerationTotal) * 100)
			: 0;

		$details = [];
		foreach($this->spawnGenerationWorldProgress as $name => $data){
			$details[] = $name . ": " . $data['done'] . "/" . $data['total'];
		}

		$message = $this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_worldLoader_progress(
			strval($progress),
			implode(" | ", $details)
		));

		Terminal::write("\r" . $this->formatWorldLoaderLine($message) . "\033[K");
		$this->progressLineVisible = true;
	}

	private function formatWorldLoaderLine(string $message) : string{
		$time = (new \DateTime('now'))->format("H:i:s");
		$timeGradient = $this->applyAnsiGradient($time, [
			[32, 228, 243],
			[42, 219, 242],
			[52, 210, 240],
			[62, 201, 239],
			[73, 191, 238],
			[83, 182, 237],
			[93, 173, 235],
			[103, 164, 234],
		]);

		$worldLoader = $this->applyAnsiGradient("WorldLoader", [
			[255, 242, 250],
			[245, 228, 246],
			[235, 213, 241],
			[225, 199, 237],
			[215, 184, 233],
			[205, 170, 229],
			[194, 155, 224],
			[184, 141, 220],
			[174, 126, 216],
			[164, 112, 211],
			[154, 97, 207],
		]);

		return TextFormat::GRAY . "(" . $timeGradient . TextFormat::GRAY . ") [" .
			$worldLoader . TextFormat::GRAY . "] " . TextFormat::WHITE . $message . TextFormat::RESET;
	}

	/**
	 * @param list<array{0: int, 1: int, 2: int}> $colors
	 */
	private function applyAnsiGradient(string $text, array $colors) : string{
		$result = "";
		$length = strlen($text);
		$colorCount = count($colors);

		for($i = 0; $i < $length; $i++){
			$colorIndex = $colorCount === 1 ? 0 : (int) round($i / max(1, $length - 1) * ($colorCount - 1));
			[$r, $g, $b] = $colors[$colorIndex];
			$result .= "\x1b[38;2;{$r};{$g};{$b}m" . $text[$i];
		}

		return $result . "\x1b[0m";
	}

	private function finishSpawnGeneration() : void{
		$this->spawnGenerationInProgress = false;
		$this->spawnGenerationFinished = true;

		if($this->progressLineVisible){
			Terminal::write("\r\033[K");
			$this->progressLineVisible = false;
		}

		$success = $this->server->getLanguage()->translate(KnownTranslationFactory::pocketmine_level_worldLoader_success());
		Terminal::writeLine($this->formatWorldLoaderLine($success));

		if(self::$consoleProgressOwner === $this){
			self::$consoleProgressOwner = null;
		}
	}

	/**
	 * Called by MainLogger before writing to the console.
	 */
	public static function notifyBeforeConsoleLog() : void{
		$owner = self::$consoleProgressOwner;
		if($owner !== null && $owner->progressLineVisible){
			Terminal::write("\r\033[K");
			$owner->progressLineVisible = false;
		}
	}

	/**
	 * Called by MainLogger after writing to the console.
	 */
	public static function notifyAfterConsoleLog() : void{
		// NOOP
	}
}
