<?php

/*
 *▒█░░░ ▒█░▒█ ▒█▄░▒█ ░█▀▀█ ▒█▀▀█ ▒█░░▒█
 *▒█░░░ ▒█░▒█ ▒█▒█▒█ ▒█▄▄█ ▒█░░░ ▒█▄▄▄█
 *▒█▄▄█ ░▀▄▄▀ ▒█░░▀█ ▒█░▒█ ▒█▄▄█ ░░▒█░░
 */

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Durable;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\Explosion;
use pocketmine\world\sound\IgniteSound;
use function mt_rand;
use function sqrt;

/** TNT minecart — ignited by activator rail, flint and steel, or explosion damage. */
class TNTMinecart extends Minecart{

	private const FUSE_TICKS = 80;

	public static function getNetworkTypeId() : string{
		return EntityIds::TNT_MINECART;
	}

	public function getName() : string{ return "Minecart with TNT"; }

	private int $fuse = -1;

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->fuse = $nbt->getInt("Fuse", -1);
		$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::IGNITED, false);
	}

	protected function sendSpawnPacket(Player $player) : void{
		$typeConverter = $player->getNetworkSession()->getTypeConverter();
		$runtimeId = $typeConverter->getBlockTranslator()->internalIdToNetworkId(VanillaBlocks::TNT()->getStateId());

		$props = $this->getNetworkProperties();
		$props->setByte(EntityMetadataProperties::MINECART_HAS_DISPLAY, 1);
		$props->setInt(EntityMetadataProperties::MINECART_DISPLAY_BLOCK, $runtimeId);
		$props->setInt(EntityMetadataProperties::MINECART_DISPLAY_OFFSET, 6);
		$props->setGenericFlag(EntityMetadataFlags::IGNITED, $this->fuse >= 0);
		$props->clearDirtyProperties();

		parent::sendSpawnPacket($player);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt("Fuse", $this->fuse);
		return $nbt;
	}

	protected function onActivatorRail(int $x, int $y, int $z) : void{
		if($this->fuse < 0){
			$this->ignite();
		}
	}

	public function ignite() : void{
		$this->fuse = self::FUSE_TICKS;
		$this->getWorld()->addSound($this->location, new IgniteSound());
		$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::IGNITED, true);
		$this->networkPropertiesDirty = true;
	}

	public function isIgnited() : bool{
		return $this->fuse >= 0;
	}

	public function onInteract(Player $player, Vector3 $clickPos) : bool{
		$item = $player->getInventory()->getItemInHand();
		if($item->getTypeId() === VanillaItems::FLINT_AND_STEEL()->getTypeId() && !$this->isIgnited()){
			$this->ignite();
			if($item instanceof Durable){
				$item->applyDamage(1);
				$player->getInventory()->setItemInHand($item);
			}
			return true;
		}
		return parent::onInteract($player, $clickPos);
	}

	public function mountPlayer(Player $player) : void{
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if($this->fuse <= 0){
			return $hasUpdate;
		}

		$this->fuse -= $tickDiff;
		if($this->fuse % 5 === 0){
			$this->getNetworkProperties()->setInt(EntityMetadataProperties::FUSE_LENGTH, $this->fuse);
			$this->networkPropertiesDirty = true;
		}

		if($this->fuse <= 0){
			$this->explode();
			return false;
		}

		return $hasUpdate;
	}

	public function explode() : void{
		if($this->closed){
			return;
		}

		$speed = sqrt($this->motion->x * $this->motion->x + $this->motion->z * $this->motion->z);
		$radius = 4.0 + (mt_rand(0, 150) / 100.0);
		if($speed > 0.01){
			$bonus = sqrt($speed) * 5.0;
			$radius += $bonus > 5.0 ? 5.0 : $bonus;
		}

		$explosion = new Explosion($this->getPosition(), $radius, $this);
		$explosion->explodeA();
		$explosion->explodeB();
		$this->flagForDespawn();
	}

	protected function onDeath() : void{
		$this->dropMinecartItem();
	}

	protected function dropMinecartItem() : void{
		$this->getWorld()->dropItem($this->location, VanillaItems::TNT_MINECART());
	}

	public function attack(EntityDamageEvent $source) : void{
		parent::attack($source);
		if(!$source->isCancelled() && !$this->isIgnited()){
			$cause = $source->getCause();
			if($cause === EntityDamageEvent::CAUSE_PROJECTILE
				|| $cause === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION
				|| $cause === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION
			){
				$this->ignite();
			}
		}
	}
}
