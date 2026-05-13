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

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\Server;
use pocketmine\utils\Process;
use pocketmine\utils\TextFormat;
use function array_sum;
use function count;
use function max;
use function number_format;
use function round;
use function str_repeat;

final class PerfCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"perf",
			"Краткая сводка производительности: TPS, сеть, чанки, память"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_STATUS);
		$this->setAliases(["performance", "производительность"]);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		$server = $sender->getServer();

		$tpsCurrent = $server->getTicksPerSecond();
		$tpsAverage = $server->getTicksPerSecondAverage();
		$loadCurrent = $server->getTickUsage();
		$loadAverage = $server->getTickUsageAverage();
		$msptCurrent = self::estimateMspt($tpsCurrent, $loadCurrent);
		$msptAverage = self::estimateMspt($tpsAverage, $loadAverage);

		$packetQueueTotal = 0;
		$gamePacketQueue = 0;
		$compressedQueue = 0;
		$ackQueue = 0;
		foreach($server->getNetwork()->getSessionManager()->getSessions() as $session){
			$packetQueueTotal += $session->getPendingPacketQueueCount();
			$gamePacketQueue += $session->getPendingGamePacketCount();
			$compressedQueue += $session->getPendingCompressedBatchCount();
			$ackQueue += $session->getPendingAckPromiseCount();
		}

		$chunkGenActive = 0;
		$chunkGenQueued = 0;
		$chunkGenCap = 0;
		$worldCount = 0;
		foreach($server->getWorldManager()->getWorlds() as $world){
			$worldCount++;
			$chunkGenActive += $world->getActiveChunkPopulationTaskCount();
			$chunkGenQueued += $world->getQueuedChunkPopulationTaskCount();
			$chunkGenCap += $world->getMaxConcurrentChunkPopulationTasks();
		}

		$pool = $server->getAsyncPool();
		$asyncQueueSizes = $pool->getTaskQueueSizes();
		$asyncQueued = array_sum($asyncQueueSizes);
		$asyncWorkers = count($pool->getRunningWorkers());

		$memory = Process::getAdvancedMemoryUsage();
		$mainMemory = self::formatMb($memory[0]);
		$totalMemory = self::formatMb($memory[1]);
		$virtualMemory = self::formatMb($memory[2]);
		$memoryLimit = $server->getMemoryManager()->getGlobalMemoryLimit();
		$memoryLimitText = $memoryLimit > 0 ? self::formatMb($memoryLimit) . " МБ" : "выкл.";

		$bar = TextFormat::DARK_GRAY . str_repeat("─", 28);
		$accent = TextFormat::LIGHT_PURPLE;
		$label = TextFormat::GRAY;
		$val = TextFormat::WHITE;
		$muted = TextFormat::DARK_GRAY;
		$dim = TextFormat::GRAY;
		$loadColor = self::loadColor($loadCurrent);

		$sender->sendMessage($bar);
		$sender->sendMessage($accent . TextFormat::BOLD . "Производительность" . TextFormat::RESET . $dim . "  ·  мгновенный снимок");
		$sender->sendMessage($bar);

		$sender->sendMessage($label . "Тик сервера");
		$sender->sendMessage(" " . $dim . "MSPT " . $muted . "·" . " " . $loadColor . $msptCurrent . TextFormat::RESET . $dim . " мс  (средн. " . TextFormat::YELLOW . $msptAverage . $dim . " мс)");
		$sender->sendMessage(" " . $dim . "TPS  " . $muted . "·" . " " . $loadColor . $tpsCurrent . TextFormat::RESET . $dim . "     (средн. " . TextFormat::YELLOW . $tpsAverage . $dim . ")");
		$sender->sendMessage(" " . $dim . "Цикл " . $muted . "·" . " " . $loadColor . $loadCurrent . "%" . TextFormat::RESET . $dim . " загрузки  (средн. " . TextFormat::YELLOW . $loadAverage . "%" . $dim . ")");

		$sender->sendMessage("");
		$sender->sendMessage($label . "Сеть" . $dim . "  (все сессии, исходящие очереди)");
		$sender->sendMessage(" " . $val . $packetQueueTotal . $dim . " пакетов всего" . $muted . " · " . $dim . "игра: " . $val . $gamePacketQueue . $dim . ", сжатые пакеты: " . $val . $compressedQueue . $dim . ", ожидание ACK: " . $val . $ackQueue);

		$sender->sendMessage("");
		$sender->sendMessage($label . "Чанки" . $dim . "  · популяция (сумма по мирам)");
		$sender->sendMessage(" " . $dim . "В работе: " . $val . $chunkGenActive . $dim . " из " . $val . $chunkGenCap . $dim . " слотов параллельно  (" . $val . $worldCount . $dim . " " . self::pluralWorlds($worldCount) . ")");
		$sender->sendMessage(" " . $dim . "В очереди на старт: " . $val . $chunkGenQueued);

		$sender->sendMessage("");
		$sender->sendMessage($label . "Асинхронный пул");
		$sender->sendMessage(" " . $dim . "Воркеры: " . $val . $asyncWorkers . $muted . " · " . $dim . "задач в очереди: " . $val . $asyncQueued);

		$sender->sendMessage("");
		$sender->sendMessage($label . "Память (оценка PHP)");
		$sender->sendMessage(" " . $dim . "Основная куча: " . $val . $mainMemory . $dim . " МБ" . $muted . " · " . $dim . "процесс: " . $val . $totalMemory . $dim . " МБ" . $muted . " · " . $dim . "вирт.: " . $val . $virtualMemory . $dim . " МБ");
		$sender->sendMessage(" " . $dim . "Лимит: " . $val . $memoryLimitText);

		$sender->sendMessage("");

		$sender->sendMessage($bar);

		return true;
	}

	private static function pluralWorlds(int $n) : string{
		$m = $n % 100;
		$m1 = $n % 10;
		if($m >= 11 && $m <= 14){
			return "миров";
		}
		return match ($m1){
			1 => "мир",
			2, 3, 4 => "мира",
			default => "миров",
		};
	}

	private static function estimateMspt(float $tps, float $loadPercent) : float{
		if($tps < Server::TARGET_TICKS_PER_SECOND - 0.01){
			return round(1000 / max(0.001, $tps), 2);
		}

		return round((Server::TARGET_SECONDS_PER_TICK * 1000) * ($loadPercent / 100), 2);
	}

	private static function formatMb(int $bytes) : string{
		return number_format(round(($bytes / 1024) / 1024, 2), 2);
	}

	private static function loadColor(float $loadPercent) : string{
		if($loadPercent < 55){
			return TextFormat::GREEN;
		}
		if($loadPercent < 80){
			return TextFormat::YELLOW;
		}
		if($loadPercent < 95){
			return TextFormat::GOLD;
		}
		return TextFormat::RED;
	}
}
