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

namespace pocketmine\network\mcpe;

use pocketmine\network\mcpe\protocol\ProtocolInfo;

/**
 * Maps Bedrock network protocol IDs to human-readable game versions for kick messages.
 *
 * @internal
 */
final class ProtocolVersionMapper{

	public const PROTOCOL_1_26_30 = 1001;

	/**
	 * @var int[]
	 * @phpstan-var list<int>
	 */
	public const ACCEPTED_PROTOCOLS = [
		self::PROTOCOL_1_26_30,
		ProtocolInfo::PROTOCOL_1_26_20,
		ProtocolInfo::PROTOCOL_1_26_10,
		ProtocolInfo::PROTOCOL_1_26_0,
		ProtocolInfo::PROTOCOL_1_21_130,
		ProtocolInfo::PROTOCOL_1_21_124,
		ProtocolInfo::PROTOCOL_1_21_120,
		ProtocolInfo::PROTOCOL_1_21_111,
		ProtocolInfo::PROTOCOL_1_21_100,
		ProtocolInfo::PROTOCOL_1_21_93,
		ProtocolInfo::PROTOCOL_1_21_90,
		ProtocolInfo::PROTOCOL_1_21_80,
		ProtocolInfo::PROTOCOL_1_21_70,
		ProtocolInfo::PROTOCOL_1_21_60,
		ProtocolInfo::PROTOCOL_1_21_50,
		ProtocolInfo::PROTOCOL_1_21_40,
		ProtocolInfo::PROTOCOL_1_21_30,
		ProtocolInfo::PROTOCOL_1_21_20,
		ProtocolInfo::PROTOCOL_1_21_2,
		ProtocolInfo::PROTOCOL_1_21_0,
		ProtocolInfo::PROTOCOL_1_20_80,
		ProtocolInfo::PROTOCOL_1_20_70,
		ProtocolInfo::PROTOCOL_1_20_60,
		ProtocolInfo::PROTOCOL_1_20_50,
		ProtocolInfo::PROTOCOL_1_20_40,
		ProtocolInfo::PROTOCOL_1_20_30,
		ProtocolInfo::PROTOCOL_1_20_10,
		ProtocolInfo::PROTOCOL_1_20_0,
	];

	private const VERSION_NAMES = [
		self::PROTOCOL_1_26_30 => "1.26.30",
		ProtocolInfo::PROTOCOL_1_26_20 => "1.26.20",
		ProtocolInfo::PROTOCOL_1_26_10 => "1.26.10",
		ProtocolInfo::PROTOCOL_1_26_0 => "1.26.0",
		ProtocolInfo::PROTOCOL_1_21_130 => "1.21.130",
		ProtocolInfo::PROTOCOL_1_21_124 => "1.21.124",
		ProtocolInfo::PROTOCOL_1_21_120 => "1.21.120",
		ProtocolInfo::PROTOCOL_1_21_111 => "1.21.111",
		ProtocolInfo::PROTOCOL_1_21_100 => "1.21.100",
		ProtocolInfo::PROTOCOL_1_21_93 => "1.21.93",
		ProtocolInfo::PROTOCOL_1_21_90 => "1.21.90",
		ProtocolInfo::PROTOCOL_1_21_80 => "1.21.80",
		ProtocolInfo::PROTOCOL_1_21_70 => "1.21.70",
		ProtocolInfo::PROTOCOL_1_21_60 => "1.21.60",
		ProtocolInfo::PROTOCOL_1_21_50 => "1.21.50",
		ProtocolInfo::PROTOCOL_1_21_40 => "1.21.40",
		ProtocolInfo::PROTOCOL_1_21_30 => "1.21.30",
		ProtocolInfo::PROTOCOL_1_21_20 => "1.21.20",
		ProtocolInfo::PROTOCOL_1_21_2 => "1.21.2",
		ProtocolInfo::PROTOCOL_1_21_0 => "1.21.0",
		ProtocolInfo::PROTOCOL_1_20_80 => "1.20.80",
		ProtocolInfo::PROTOCOL_1_20_70 => "1.20.70",
		ProtocolInfo::PROTOCOL_1_20_60 => "1.20.60",
		ProtocolInfo::PROTOCOL_1_20_50 => "1.20.50",
		ProtocolInfo::PROTOCOL_1_20_40 => "1.20.40",
		ProtocolInfo::PROTOCOL_1_20_30 => "1.20.30",
		ProtocolInfo::PROTOCOL_1_20_10 => "1.20.10",
		ProtocolInfo::PROTOCOL_1_20_0 => "1.20.0",
	];

	private function __construct(){
		//NOOP
	}

	public static function getVersionName(int $protocolId) : string{
		return self::VERSION_NAMES[$protocolId] ?? ("unknown (" . $protocolId . ")");
	}

	public static function isAcceptedProtocol(int $protocolId) : bool{
		return in_array($protocolId, self::ACCEPTED_PROTOCOLS, true);
	}

	public static function getMinProtocol() : int{
		return ProtocolInfo::PROTOCOL_1_20_0;
	}

	public static function getMaxProtocol() : int{
		return self::PROTOCOL_1_26_30;
	}

	public static function getSupportedVersionRange() : string{
		return self::getVersionName(self::getMinProtocol()) . " - " . self::getVersionName(self::getMaxProtocol());
	}

	public static function getSupportedProtocolRange() : string{
		return self::getMinProtocol() . " - " . self::getMaxProtocol();
	}
}
