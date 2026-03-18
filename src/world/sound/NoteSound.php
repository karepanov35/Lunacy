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
namespace pocketmine\world\sound;

use pocketmine\data\bedrock\NoteInstrumentIdMap;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

class NoteSound extends ProtocolSound{
	public function __construct(
		private NoteInstrument $instrument,
		private int $note
	){
		if($this->note < 0 || $this->note > 255){
			throw new \InvalidArgumentException("Note $note is outside accepted range");
		}
	}

	public function encode(Vector3 $pos) : array{
		$instrumentId = NoteInstrumentIdMap::getInstance()->toId($this->instrument);

		if($this->protocolId < ProtocolInfo::PROTOCOL_1_21_50){
			if($instrumentId === 5 || $instrumentId === 7){
				$instrumentId++;
			}elseif($instrumentId === 6 || $instrumentId === 8){
				$instrumentId--;
			}
		}

		return [LevelSoundEventPacket::nonActorSound(LevelSoundEvent::NOTE, $pos, false, ($instrumentId << 8) | $this->note)];
	}
}
