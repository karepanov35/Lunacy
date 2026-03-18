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
namespace pocketmine\world\generator\object;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;

class OakTree extends Tree{

	public function __construct(){
		parent::__construct(VanillaBlocks::OAK_LOG(), VanillaBlocks::OAK_LEAVES());
	}

	public function getBlockTransaction(ChunkManager $world, int $x, int $y, int $z, Random $random) : ?BlockTransaction{
		// Tree size variation: small (4-5), medium (6-7), large (8-10)
		$sizeType = $random->nextBoundedInt(10);
		if($sizeType < 3){ // 30% small trees
			$this->treeHeight = $random->nextBoundedInt(2) + 4; // 4-5 blocks
		}elseif($sizeType < 7){ // 40% medium trees
			$this->treeHeight = $random->nextBoundedInt(2) + 6; // 6-7 blocks
		}else{ // 30% large trees
			$this->treeHeight = $random->nextBoundedInt(3) + 8; // 8-10 blocks
		}
		return parent::getBlockTransaction($world, $x, $y, $z, $random);
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, BlockTransaction $transaction) : void{
		// More beautiful and lush canopy for oak trees
		$topY = $y + $this->treeHeight;
		
		// Top layer (single block or small cross)
		$transaction->addBlockAt($x, $topY, $z, $this->leafBlock);
		if($random->nextBoundedInt(2) === 0){
			$transaction->addBlockAt($x + 1, $topY, $z, $this->leafBlock);
			$transaction->addBlockAt($x - 1, $topY, $z, $this->leafBlock);
			$transaction->addBlockAt($x, $topY, $z + 1, $this->leafBlock);
			$transaction->addBlockAt($x, $topY, $z - 1, $this->leafBlock);
		}
		
		// Upper middle layers - fuller and rounder
		for($yy = $topY - 1; $yy >= $topY - 2; --$yy){
			$radius = 2;
			for($xx = $x - $radius; $xx <= $x + $radius; ++$xx){
				for($zz = $z - $radius; $zz <= $z + $radius; ++$zz){
					$xOff = abs($xx - $x);
					$zOff = abs($zz - $z);
					
					// Skip corners for rounder shape
					if($xOff === $radius && $zOff === $radius){
						if($random->nextBoundedInt(3) !== 0) continue;
					}
					
					if(!$transaction->fetchBlockAt($xx, $yy, $zz)->isSolid()){
						$transaction->addBlockAt($xx, $yy, $zz, $this->leafBlock);
					}
				}
			}
		}
		
		// Lower layers - wider and more spread out
		for($yy = $topY - 3; $yy >= $topY - 4; --$yy){
			if($yy < $y) break;
			
			$radius = 3;
			for($xx = $x - $radius; $xx <= $x + $radius; ++$xx){
				for($zz = $z - $radius; $zz <= $z + $radius; ++$zz){
					$xOff = abs($xx - $x);
					$zOff = abs($zz - $z);
					
					// Skip far corners
					if($xOff === $radius && $zOff === $radius){
						continue;
					}
					
					// Random gaps for natural look
					if($xOff === $radius || $zOff === $radius){
						if($random->nextBoundedInt(2) === 0) continue;
					}
					
					if(!$transaction->fetchBlockAt($xx, $yy, $zz)->isSolid()){
						$transaction->addBlockAt($xx, $yy, $zz, $this->leafBlock);
					}
				}
			}
		}
		
		// Add some hanging leaves below for extra beauty
		if($this->treeHeight >= 6){
			for($i = 0; $i < 3; ++$i){
				$offsetX = $random->nextBoundedInt(5) - 2;
				$offsetZ = $random->nextBoundedInt(5) - 2;
				$hangY = $topY - 4 - $random->nextBoundedInt(2);
				
				if($hangY >= $y && abs($offsetX) + abs($offsetZ) <= 3){
					$hangX = $x + $offsetX;
					$hangZ = $z + $offsetZ;
					if(!$transaction->fetchBlockAt($hangX, $hangY, $hangZ)->isSolid()){
						$transaction->addBlockAt($hangX, $hangY, $hangZ, $this->leafBlock);
					}
				}
			}
		}
	}
}
