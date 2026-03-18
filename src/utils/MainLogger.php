<?php

declare(strict_types=1);

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

namespace pocketmine\utils;

use pmmp\thread\Thread as NativeThread;
use pocketmine\thread\log\AttachableThreadSafeLogger;
use pocketmine\thread\log\ThreadSafeLoggerAttachment;
use pocketmine\thread\Thread;
use pocketmine\thread\Worker;
use function implode;
use function sprintf;
use const PHP_EOL;

class MainLogger extends AttachableThreadSafeLogger implements \BufferedLogger{
	protected bool $logDebug;

	private string $format = TextFormat::GRAY . "(%s) " . TextFormat::RESET . "%s[%s/%s]: %s" . TextFormat::RESET;
	private bool $useFormattingCodes = false;
	private string $mainThreadName;
	private string $timezone;
	private ?MainLoggerThread $logWriterThread = null;

	public function __construct(?string $logFile, bool $useFormattingCodes, string $mainThreadName, \DateTimeZone $timezone, bool $logDebug = false, ?string $logArchiveDir = null){
		parent::__construct();
		$this->logDebug = $logDebug;

		$this->useFormattingCodes = $useFormattingCodes;
		$this->mainThreadName = $mainThreadName;
		$this->timezone = $timezone->getName();

		if($logFile !== null){
			$this->logWriterThread = new MainLoggerThread($logFile, $logArchiveDir);
			$this->logWriterThread->start(NativeThread::INHERIT_NONE);
		}
	}

	public function getFormat() : string{
		return $this->format;
	}

	public function setFormat(string $format) : void{
		$this->format = $format;
	}

	public function emergency($message){
		$this->send($message, \LogLevel::EMERGENCY, "EMERGENCY", TextFormat::RED);
	}

	public function alert($message){
		$this->send($message, \LogLevel::ALERT, "ALERT", TextFormat::RED);
	}

	public function critical($message){
		$this->send($message, \LogLevel::CRITICAL, "CRITICAL", TextFormat::RED);
	}

	public function error($message){
		$this->send($message, \LogLevel::ERROR, "ERROR", TextFormat::DARK_RED);
	}

	public function warning($message){
		$this->send($message, \LogLevel::WARNING, "WARNING", TextFormat::YELLOW);
	}

	public function notice($message){
		$this->send($message, \LogLevel::NOTICE, "NOTICE", TextFormat::AQUA);
	}

	public function info($message){
		$this->send($message, \LogLevel::INFO, "INFO", TextFormat::WHITE);
	}

	public function debug($message, bool $force = false){
		if(!$this->logDebug && !$force){
			return;
		}
		$this->send($message, \LogLevel::DEBUG, "DEBUG", TextFormat::GRAY);
	}

	public function setLogDebug(bool $logDebug) : void{
		$this->logDebug = $logDebug;
	}

	public function logException(\Throwable $e, $trace = null){
		$this->critical(implode("\n", Utils::printableExceptionInfo($e, $trace)));

		$this->syncFlushBuffer();
	}

	public function log($level, $message){
		switch($level){
			case \LogLevel::EMERGENCY:
				$this->emergency($message);
				break;
			case \LogLevel::ALERT:
				$this->alert($message);
				break;
			case \LogLevel::CRITICAL:
				$this->critical($message);
				break;
			case \LogLevel::ERROR:
				$this->error($message);
				break;
			case \LogLevel::WARNING:
				$this->warning($message);
				break;
			case \LogLevel::NOTICE:
				$this->notice($message);
				break;
			case \LogLevel::INFO:
				$this->info($message);
				break;
			case \LogLevel::DEBUG:
				$this->debug($message);
				break;
		}
	}

	public function buffer(\Closure $c) : void{
		$this->synchronized($c);
	}

	public function shutdownLogWriterThread() : void{
		if($this->logWriterThread !== null){
			if(NativeThread::getCurrentThreadId() === $this->logWriterThread->getCreatorId()){
				$this->logWriterThread->shutdown();
			}else{
				throw new \LogicException("Only the creator thread can shutdown the logger thread");
			}
		}
	}

protected function send(string $message, string $level, string $prefix, string $color) : void{
		$time = new \DateTime('now', new \DateTimeZone($this->timezone));

		$thread = NativeThread::getCurrentThread();
		if($thread === null){
			$threadName = $this->mainThreadName . " thread";
		}elseif($thread instanceof Thread || $thread instanceof Worker){
			$threadName = $thread->getThreadName() . " thread";
		}else{
			$threadName = (new \ReflectionClass($thread))->getShortName() . " thread";
		}

		if(trim($threadName) === "Server thread"){
			$threadName = "\x1b[38;2;255;93;112mL\x1b[38;2;251;87;102mu\x1b[38;2;248;81;92mn\x1b[38;2;244;74;82ma\x1b[38;2;241;68;72mc\x1b[38;2;237;62;62my\x1b[0m";
		}

		$timeStr = $time->format("H:i:s");
		$timeGradient = $this->applyTimeGradient($timeStr);

		$message = sprintf($this->format, $timeGradient, $color, $threadName, $prefix, TextFormat::addBase($color, TextFormat::clean($message, false)));

		if(!Terminal::isInit()){
			Terminal::init($this->useFormattingCodes); 
		}

		$this->synchronized(function() use ($message, $level, $time) : void{
			Terminal::writeLine($message);
			if($this->logWriterThread !== null){
				$this->logWriterThread->write($time->format("Y-m-d") . " " . TextFormat::clean($message) . PHP_EOL);
			}

			foreach($this->attachments as $attachment){
				$attachment->log($level, $message);
			}
		});
	}

	private function applyTimeGradient(string $time): string {
		$colors = [
			"\x1b[38;2;32;228;243m",
			"\x1b[38;2;42;219;242m",
			"\x1b[38;2;52;210;240m",
			"\x1b[38;2;62;201;239m",
			"\x1b[38;2;73;191;238m",
			"\x1b[38;2;83;182;237m",
			"\x1b[38;2;93;173;235m",
			"\x1b[38;2;103;164;234m",
		];
		
		$result = "";
		$colorIndex = 0;
		for ($i = 0; $i < strlen($time); $i++) {
			$result .= $colors[$colorIndex] . $time[$i];
			$colorIndex++;
		}
		$result .= "\x1b[0m";
		return $result;
	}

	public function syncFlushBuffer() : void{
		$this->logWriterThread?->syncFlushBuffer();
	}

	public function __destruct(){
		if($this->logWriterThread !== null && !$this->logWriterThread->isJoined() && NativeThread::getCurrentThreadId() === $this->logWriterThread->getCreatorId()){
			$this->shutdownLogWriterThread();
		}
	}
}
