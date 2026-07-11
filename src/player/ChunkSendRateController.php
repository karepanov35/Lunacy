<?php

declare(strict_types=1);
namespace pocketmine\player;

use pocketmine\Server;

final class ChunkSendRateController{
	public function __construct(
		private int $generationPerTick,
		private int $burstPerTick,
		private bool $adaptive
	){}

	public function getBurstLimit(Server $server) : int{
		$limit = $this->burstPerTick;
		if($this->adaptive){
			$limit = $this->scaleByTps($server, $limit);
		}
		return max(1, $limit);
	}

	public function getGenerationLimit(Server $server, int $activeGenerationRequests) : int{
		$limit = $this->generationPerTick - $activeGenerationRequests;
		if($this->adaptive){
			$limit = $this->scaleByTps($server, $limit);
		}
		return max(1, $limit);
	}

	private function scaleByTps(Server $server, int $limit) : int{
		$tps = $server->getTicksPerSecondAverage();
		if($tps >= 19.5){
			return $limit;
		}
		if($tps < 15){
			return max(1, (int) ($limit * 0.5));
		}
		return max(1, (int) ($limit * ($tps / 20)));
	}
}
