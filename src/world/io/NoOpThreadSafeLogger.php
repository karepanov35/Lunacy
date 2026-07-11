<?php

declare(strict_types=1);
namespace pocketmine\world\io;

use pocketmine\thread\log\ThreadSafeLogger;

final class NoOpThreadSafeLogger extends ThreadSafeLogger{
	public function emergency($message){}
	public function alert($message){}
	public function critical($message){}
	public function error($message){}
	public function warning($message){}
	public function notice($message){}
	public function info($message){}
	public function debug($message, bool $force = false){}
	public function log($level, $message){}
	public function logException(\Throwable $e, $trace = null){}
}
