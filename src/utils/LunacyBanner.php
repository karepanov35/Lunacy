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
namespace pocketmine\utils;

use function exp;
use function flush;
use function mb_str_split;
use function usleep;
use const PHP_EOL;

final class LunacyBanner{
	private const LINES = [
		'██╗     ██╗   ██╗███╗  ██╗ █████╗  █████╗ ██╗   ██╗',
		'██║     ██║   ██║████╗ ██║██╔══██╗██╔══██╗╚██╗ ██╔╝',
		'██║     ██║   ██║██╔██╗██║███████║██║  ╚═╝ ╚████╔╝',
		'██║     ██║   ██║██║╚████║██╔══██║██║  ██╗  ╚██╔╝',
		'███████╗╚██████╔╝██║ ╚███║██║  ██║╚█████╔╝   ██║',
		'╚══════╝ ╚═════╝ ╚═╝  ╚══╝╚═╝  ╚═╝ ╚════╝    ╚═╝',
	];

	private const COLORS = [
		[255, 93, 112],
		[251, 87, 102],
		[248, 81, 92],
		[244, 74, 82],
		[241, 68, 72],
		[237, 62, 62],
	];

	private const SHINE_SIGMA = 6.0;
	private const SHINE_SIGMA_SQ2 = 72.0;
	private const SHINE_OFFSET = 7.5;
	private const SHINE_SPAN = 15.0;
	private const FRAME_COUNT = 48;
	private const FRAME_DELAY_US = 55000;

	public static function render() : void{
		echo PHP_EOL;

		if(!Terminal::hasFormattingCodes()){
			foreach(self::LINES as $i => $line){
				[$r, $g, $b] = self::COLORS[$i];
				echo "\033[38;2;{$r};{$g};{$b}m{$line}\033[0m" . PHP_EOL;
			}
			return;
		}

		$glyphs = [];
		$width = 0;
		foreach(self::LINES as $line){
			$split = mb_str_split($line, 1, 'UTF-8');
			$glyphs[] = $split;
			$width = max($width, count($split));
		}

		$travel = $width + self::SHINE_SPAN;
		$lineCount = count(self::LINES);

		echo "\033[?25l";

		for($frame = 0; $frame <= self::FRAME_COUNT; ++$frame){
			if($frame > 0){
				echo "\033[{$lineCount}A";
			}

			$center = self::easeInOut($frame / self::FRAME_COUNT) * $travel - self::SHINE_OFFSET;

			foreach($glyphs as $i => $lineGlyphs){
				[$br, $bg, $bb] = self::COLORS[$i];
				$buffer = '';

				foreach($lineGlyphs as $col => $char){
					$dist = abs($col - $center);
					$blend = min(0.92, exp(-($dist * $dist) / self::SHINE_SIGMA_SQ2) * 1.15);
					$r = (int) ($br + (255 - $br) * $blend);
					$g = (int) ($bg + (255 - $bg) * $blend);
					$b = (int) ($bb + (255 - $bb) * $blend);
					$buffer .= "\033[38;2;{$r};{$g};{$b}m{$char}";
				}

				echo $buffer . "\033[0m" . PHP_EOL;
			}

			if($frame < self::FRAME_COUNT){
				flush();
				usleep(self::FRAME_DELAY_US);
			}
		}

		echo "\033[?25h";
	}

	private static function easeInOut(float $t) : float{
		if($t < 0.5){
			return 2 * $t * $t;
		}

		$v = 2 - 2 * $t;
		return 1 - $v * $v / 2;
	}
}
