<?php

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;

/**
 * /biome [id] — текущий биом и расстояние до ближайшего указанного биома.
 */
class BiomeCommand extends VanillaCommand{

	private const MAX_SEARCH_CHUNKS = 50;

	public function __construct(){
		parent::__construct(
			"biome",
			"Показывает текущий биом и расстояние до указанного биома (по ID)"
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_BIOME);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage("Эта команда только для игроков.");
			return true;
		}

		$world = $sender->getPosition()->getWorld();
		$bx = $sender->getPosition()->getFloorX();
		$by = $sender->getPosition()->getFloorY();
		$bz = $sender->getPosition()->getFloorZ();

		$currentId = $world->getBiomeId($bx, $by, $bz);
		$currentName = $this->biomeIdToName($currentId);

		if(count($args) === 0){
			$sender->sendMessage("Текущий биом: §e" . $currentName . " §7(ID: " . $currentId . ")");
			$sender->sendMessage("Использование: §f/biome <id> §7— расстояние до ближайшего биома с указанным ID.");
			return true;
		}

		$targetId = (int) $args[0];
		$targetName = $this->biomeIdToName($targetId);

		if($currentId === $targetId){
			$sender->sendMessage("Вы уже в биоме §e" . $targetName . "§7.");
			return true;
		}

		$chunkX = $bx >> Chunk::COORD_BIT_SIZE;
		$chunkZ = $bz >> Chunk::COORD_BIT_SIZE;

		for($r = 1; $r <= self::MAX_SEARCH_CHUNKS; $r++){
			for($dx = -$r; $dx <= $r; $dx++){
				foreach([-$r, $r] as $dz){
					$cx = $chunkX + $dx;
					$cz = $chunkZ + $dz;
					$id = $world->getBiomeId(($cx << 4) + 8, min(64, $world->getMaxY() - 1), ($cz << 4) + 8);
					if($id === $targetId){
						$centerX = ($cx << 4) + 8;
						$centerZ = ($cz << 4) + 8;
						$dist = (int) round(sqrt(($bx - $centerX) ** 2 + ($bz - $centerZ) ** 2));
						$sender->sendMessage("До биома §e" . $targetName . "§7 (ID: " . $targetId . "): §f~" . $dist . " §7блоков.");
						return true;
					}
				}
			}
			for($dz = -$r; $dz <= $r; $dz++){
				foreach([-$r, $r] as $dx){
					$cx = $chunkX + $dx;
					$cz = $chunkZ + $dz;
					$id = $world->getBiomeId(($cx << 4) + 8, min(64, $world->getMaxY() - 1), ($cz << 4) + 8);
					if($id === $targetId){
						$centerX = ($cx << 4) + 8;
						$centerZ = ($cz << 4) + 8;
						$dist = (int) round(sqrt(($bx - $centerX) ** 2 + ($bz - $centerZ) ** 2));
						$sender->sendMessage("До биома §e" . $targetName . "§7 (ID: " . $targetId . "): §f~" . $dist . " §7блоков.");
						return true;
					}
				}
			}
		}

		$sender->sendMessage("Биом §e" . $targetName . "§7 не найден в радиусе ~" . (self::MAX_SEARCH_CHUNKS * 16) . " блоков.");
		return true;
	}

	private function biomeIdToName(int $id) : string{
		$names = [
			0 => "Ocean", 1 => "Plains", 2 => "Desert", 3 => "Extreme Hills", 4 => "Forest", 5 => "Taiga",
			6 => "Swampland", 7 => "River", 8 => "Hell", 9 => "The End", 10 => "Frozen Ocean", 11 => "Frozen River",
			12 => "Ice Plains", 13 => "Ice Mountains", 14 => "Mushroom Island", 15 => "Mushroom Shore", 16 => "Beach",
			17 => "Desert Hills", 18 => "Forest Hills", 19 => "Taiga Hills", 20 => "Extreme Hills Edge", 21 => "Jungle",
			22 => "Jungle Hills", 23 => "Jungle Edge", 24 => "Deep Ocean", 25 => "Stone Beach", 26 => "Cold Beach",
			27 => "Birch Forest", 28 => "Birch Forest Hills", 29 => "Roofed Forest", 30 => "Cold Taiga", 31 => "Cold Taiga Hills",
			32 => "Mega Taiga", 33 => "Mega Taiga Hills", 34 => "Extreme Hills+", 35 => "Savanna", 36 => "Savanna Plateau",
			37 => "Mesa", 38 => "Mesa Plateau Stone", 39 => "Mesa Plateau", 40 => "Warm Ocean", 41 => "Deep Warm Ocean",
			42 => "Lukewarm Ocean", 43 => "Deep Lukewarm Ocean", 44 => "Cold Ocean", 45 => "Deep Cold Ocean",
			46 => "Frozen Ocean", 47 => "Deep Frozen Ocean", 48 => "Bamboo Jungle", 49 => "Bamboo Jungle Hills",
			129 => "Sunflower Plains", 130 => "Desert M", 131 => "Extreme Hills M", 132 => "Flower Forest", 133 => "Taiga M",
			134 => "Swampland M", 140 => "Ice Plains Spikes", 149 => "Jungle M", 151 => "Jungle Edge M",
			155 => "Birch Forest M", 156 => "Birch Forest Hills M", 157 => "Roofed Forest M", 158 => "Cold Taiga M",
			160 => "Redwood Taiga M", 161 => "Redwood Taiga Hills M", 162 => "Extreme Hills+ M", 163 => "Savanna M",
			164 => "Savanna Plateau M", 165 => "Mesa Bryce", 166 => "Mesa Plateau Stone M", 167 => "Mesa Plateau M",
		];
		return $names[$id] ?? "ID:" . $id;
	}
}
