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
namespace pocketmine\network;

final class BidirectionalBandwidthStatsTracker{
	private BandwidthStatsTracker $send;
	private BandwidthStatsTracker $receive;

	/** @phpstan-param positive-int $historySize */
	public function __construct(int $historySize){
		$this->send = new BandwidthStatsTracker($historySize);
		$this->receive = new BandwidthStatsTracker($historySize);
	}

	public function getSend() : BandwidthStatsTracker{ return $this->send; }

	public function getReceive() : BandwidthStatsTracker{ return $this->receive; }

	public function add(int $sendBytes, int $recvBytes) : void{
		$this->send->add($sendBytes);
		$this->receive->add($recvBytes);
	}

	/** @see BandwidthStatsTracker::rotateHistory() */
	public function rotateAverageHistory() : void{
		$this->send->rotateHistory();
		$this->receive->rotateHistory();
	}

	/** @see BandwidthStatsTracker::resetHistory() */
	public function resetHistory() : void{
		$this->send->resetHistory();
		$this->receive->resetHistory();
	}
}
