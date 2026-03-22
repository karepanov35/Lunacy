<?php
declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\block\BlockTypeIds;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\world\sound\BowShootSound;
use function atan2;
use function mt_rand;
use function sqrt;
use function abs;
use function rad2deg;
use function deg2rad;
use function sin;
use function cos;
use function floor;

class Skeleton extends Living {
    private Item $heldItem;
    private ?Player $target = null;
    private int $attackCooldown = 0;
    private bool $isAiming = false;
    private int $aimingTicks = 0;
    
    private int $idleTime = 0;
    private ?Vector3 $wanderTarget = null;
    private int $jumpCooldown = 0;
    private int $fireTickCooldown = 0;
    /** Кулдаун после смены направления из-за стены, чтобы не дёргаться */
    private int $obstacleAvoidCooldown = 0;
    /** Текущее сглаженное направление движения (для плавного поворота) */
    private float $smoothMotionX = 0.0;
    private float $smoothMotionZ = 0.0;

    public static function getNetworkTypeId() : string {
        return EntityIds::SKELETON;
    }

    protected function getInitialSizeInfo() : EntitySizeInfo {
        return new EntitySizeInfo(1.99, 0.6);
    }

    public function getName() : string {
        return "Skeleton";
    }

    protected function initEntity(CompoundTag $nbt) : void {
        parent::initEntity($nbt);
        $this->heldItem = VanillaItems::BOW();
        $this->setMaxHealth(20);
        $this->setHealth(20);
        // 1.0 — иначе скелет не залезает на блок: move() обнуляет горизонтальный motion при коллизии, шаг 0.6 не хватает
        $this->setStepHeight(1.0);
        
        // РУКИ: Гарантированно опущены при спавне
        $this->isAiming = false;
    }

    protected function syncNetworkData(EntityMetadataCollection $properties) : void {
        parent::syncNetworkData($properties);
        
        // Флаг CHARGE_ATTACK (43) — Bedrock анимация поднятых рук для лука
        $properties->setGenericFlag(EntityMetadataFlags::CHARGE_ATTACK, $this->isAiming);
        
        if($this->isAiming && $this->target !== null){
            $properties->setLong(EntityMetadataProperties::TARGET_EID, $this->target->getId());
        } else {
            // ПРИНУДИТЕЛЬНЫЙ СБРОС (Руки вниз)
            $properties->setLong(EntityMetadataProperties::TARGET_EID, -1);
        }
    }

    protected function entityBaseTick(int $tickDiff = 1) : bool {
        $hasUpdate = parent::entityBaseTick($tickDiff);
        if(!$this->isAlive()) return $hasUpdate;

        $this->checkDaylightBurning();

        // Проверка цели
        if($this->target !== null){
            if(!$this->target->isAlive() || $this->target->isClosed() || $this->target->isCreative() || $this->location->distanceSquared($this->target->getPosition()) > 625){
                $this->target = null;
                $this->isAiming = false;
                $this->networkPropertiesDirty = true;
            }
        }
        
        if($this->obstacleAvoidCooldown > 0) $this->obstacleAvoidCooldown -= $tickDiff;

        if($this->target === null){
            $this->findNearestPlayer();
            $this->wanderAI();
        } else {
            $this->combatAI();
        }

        $this->handleVanillaPhysics();

        if($this->attackCooldown > 0) $this->attackCooldown -= $tickDiff;

        return true;
    }

    /**
     * Прыжок только для препятствий ВЫШЕ 1 блока. На 1 блок скелет забирается за счёт stepHeight=1.0 в move().
     * При прыжке motion.x/z в move() обнуляются при коллизии, поэтому на 1 блок полагаемся на шаг.
     */
    private function handleVanillaPhysics() : void {
        if(!$this->onGround || $this->jumpCooldown > 0) {
            if($this->jumpCooldown > 0) $this->jumpCooldown--;
            return;
        }

        $motionLength = sqrt($this->motion->x ** 2 + $this->motion->z ** 2);
        if($motionLength < 0.05) return;

        $dirX = $this->motion->x / $motionLength;
        $dirZ = $this->motion->z / $motionLength;

        $checkX = $this->location->x + ($dirX * 0.5);
        $checkZ = $this->location->z + ($dirZ * 0.5);
        $world = $this->getWorld();

        $blockFoot = $world->getBlockAt((int)floor($checkX), (int)floor($this->location->y), (int)floor($checkZ));
        $blockHead = $world->getBlockAt((int)floor($checkX), (int)floor($this->location->y + 1.5), (int)floor($checkZ));
        $blockAbove = $world->getBlockAt((int)floor($this->location->x), (int)floor($this->location->y + 2), (int)floor($this->location->z));

        if(!$blockFoot->isSolid() || $blockHead->isSolid() || $blockAbove->isSolid()) {
            return;
        }

        $obstacleTopY = $blockFoot->getPosition()->y + 1;
        $obstacleHeight = $obstacleTopY - $this->location->y;

        // На 1 блок забираемся шагом (stepHeight=1.0). Прыжок только для 2+ блоков.
        if($obstacleHeight <= 1.0) {
            return;
        }

        $this->jump();
        $jumpVelocity = $this->getJumpVelocity();
        $horizontal = 0.5;
        $this->motion = new Vector3($dirX * $horizontal, $jumpVelocity, $dirZ * $horizontal);
        $this->jumpCooldown = 14;
    }

    private function combatAI() : void {
        if($this->attackTime > 0){
            $this->lookAt($this->target->getEyePos());
            return;
        }

        $targetPos = $this->target->getPosition();
        $distSq = $this->location->distanceSquared($targetPos);

        $this->lookAt($this->target->getEyePos());

        if($distSq < 36) {
            $this->moveAwayFrom($targetPos);
        } elseif($distSq > 100) {
            $dirX = $targetPos->x - $this->location->x;
            $dirZ = $targetPos->z - $this->location->z;
            $len = sqrt($dirX * $dirX + $dirZ * $dirZ);
            if($len > 0.01 && $this->obstacleAvoidCooldown <= 0 && $this->hasObstacleInFront($dirX / $len, $dirZ / $len, 0.8)) {
                $avoid = $this->getAvoidanceDirection($dirX / $len, $dirZ / $len);
                if($avoid !== null) {
                    $strafe = 0.2;
                    $this->smoothMotionX = $this->smoothMotionX * 0.5 + ($avoid->x * $strafe) * 0.5;
                    $this->smoothMotionZ = $this->smoothMotionZ * 0.5 + ($avoid->z * $strafe) * 0.5;
                    $this->motion->x = $this->smoothMotionX;
                    $this->motion->z = $this->smoothMotionZ;
                    $this->obstacleAvoidCooldown = 15;
                } else {
                    $this->moveTowards($targetPos, true);
                }
            } else {
                $this->moveTowards($targetPos, true);
            }
        } else {
            $this->smoothMotionX *= 0.6;
            $this->smoothMotionZ *= 0.6;
            $this->motion->x = $this->smoothMotionX;
            $this->motion->z = $this->smoothMotionZ;
        }

        if($this->attackCooldown <= 0 && $this->canSee($this->target)){
            if(!$this->isAiming){
                $this->isAiming = true;
                $this->networkPropertiesDirty = true; 
                $this->aimingTicks = 0;
            }
            
            $this->aimingTicks++;
            if($this->aimingTicks >= 20){ 
                $this->shootArrow();
                $this->isAiming = false;
                $this->attackCooldown = 40; 
                $this->networkPropertiesDirty = true; 
            }
        }
    }

    /**
     * Есть ли именно стена (2+ блока) впереди. Ступенька в 1 блок не считается стеной — на неё можно зайти шагом.
     */
    private function hasObstacleInFront(float $dirX, float $dirZ, float $distance = 0.8) : bool {
        $len = sqrt($dirX * $dirX + $dirZ * $dirZ);
        if($len < 0.01) return false;
        $dirX /= $len;
        $dirZ /= $len;
        $world = $this->getWorld();
        $x = (int) floor($this->location->x + $dirX * $distance);
        $z = (int) floor($this->location->z + $dirZ * $distance);
        $yFoot = (int) floor($this->location->y);
        $yHead = (int) floor($this->location->y + 1.6);
        $blockFoot = $world->getBlockAt($x, $yFoot, $z)->isSolid();
        $blockHead = $world->getBlockAt($x, $yHead, $z)->isSolid();
        return $blockFoot && $blockHead;
    }

    /**
     * Направление в сторону обхода (поворот на 90° влево или вправо), проверяем что там свободно.
     */
    private function getAvoidanceDirection(float $dirX, float $dirZ) : ?Vector3 {
        $len = sqrt($dirX * $dirX + $dirZ * $dirZ);
        if($len < 0.01) return null;
        $dirX /= $len;
        $dirZ /= $len;
        $leftX = -$dirZ;
        $leftZ = $dirX;
        $rightX = $dirZ;
        $rightZ = -$dirX;
        if(!$this->hasObstacleInFront($leftX, $leftZ, 0.9)) {
            return new Vector3($leftX, 0, $leftZ);
        }
        if(!$this->hasObstacleInFront($rightX, $rightZ, 0.9)) {
            return new Vector3($rightX, 0, $rightZ);
        }
        return null;
    }

    private function moveTowards(Vector3 $target, bool $smooth = true) : void {
        $x = $target->x - $this->location->x;
        $z = $target->z - $this->location->z;
        $diff = abs($x) + abs($z);
        if($diff < 0.1) {
            if($smooth) {
                $this->motion->x = $this->smoothMotionX *= 0.6;
                $this->motion->z = $this->smoothMotionZ *= 0.6;
            }
            return;
        }
        $speed = 0.22;
        $wantX = $speed * ($x / $diff);
        $wantZ = $speed * ($z / $diff);

        if($smooth) {
            $this->smoothMotionX = $this->smoothMotionX * 0.5 + $wantX * 0.5;
            $this->smoothMotionZ = $this->smoothMotionZ * 0.5 + $wantZ * 0.5;
            $this->motion->x = $this->smoothMotionX;
            $this->motion->z = $this->smoothMotionZ;
        } else {
            $this->motion->x = $wantX;
            $this->motion->z = $wantZ;
        }

        if($this->target === null) {
            $targetYaw = rad2deg(atan2(-$x, $z));
            $this->location->yaw = $this->lerpAngle($this->location->yaw, $targetYaw, 0.15);
        }
    }

    private function lerpAngle(float $from, float $to, float $t) : float {
        $diff = $to - $from + 540.0;
        $wrapped = $diff - 360.0 * floor($diff / 360.0);
        $delta = $wrapped - 180.0;
        return $from + $delta * $t;
    }

    private function moveAwayFrom(Vector3 $target) : void {
        $x = $this->location->x - $target->x;
        $z = $this->location->z - $target->z;
        $diff = abs($x) + abs($z);
        if($diff < 0.1) {
            $this->motion->x = $this->smoothMotionX *= 0.6;
            $this->motion->z = $this->smoothMotionZ *= 0.6;
            return;
        }
        $speed = 0.18;
        $this->smoothMotionX = $this->smoothMotionX * 0.5 + ($speed * ($x / $diff)) * 0.5;
        $this->smoothMotionZ = $this->smoothMotionZ * 0.5 + ($speed * ($z / $diff)) * 0.5;
        $this->motion->x = $this->smoothMotionX;
        $this->motion->z = $this->smoothMotionZ;
    }

    private function wanderAI() : void {
        if($this->idleTime > 0) {
            $this->idleTime--;
            $this->motion->x = $this->smoothMotionX *= 0.85;
            $this->motion->z = $this->smoothMotionZ *= 0.85;
            return;
        }

        $motionLen = sqrt($this->motion->x ** 2 + $this->motion->z ** 2);
        if($this->wanderTarget !== null && $motionLen > 0.03 && $this->obstacleAvoidCooldown <= 0) {
            $dirX = $this->motion->x / $motionLen;
            $dirZ = $this->motion->z / $motionLen;
            if($this->hasObstacleInFront($dirX, $dirZ, 0.7)) {
                $avoid = $this->getAvoidanceDirection($dirX, $dirZ);
                if($avoid !== null) {
                    $this->wanderTarget = $this->location->add($avoid->x * 6, 0, $avoid->z * 6);
                    $this->obstacleAvoidCooldown = 25;
                    $this->idleTime = 5;
                }
            }
        }

        if($this->wanderTarget === null || $this->location->distanceSquared($this->wanderTarget) < 9) {
            $this->idleTime = 80;
            $angle = mt_rand(0, 359) * (M_PI / 180);
            $dist = mt_rand(6, 12);
            $this->wanderTarget = $this->location->add(cos($angle) * $dist, 0, sin($angle) * $dist);
        }

        $this->moveTowards($this->wanderTarget, true);
    }

    // --- ДРОП И ОПЫТ (API PMMP5) ---
    public function getDrops() : array {
        return [
            VanillaItems::BONE()->setCount(mt_rand(1, 2)),
            VanillaItems::ARROW()->setCount(mt_rand(0, 2))
        ];
    }

    public function getXpDropAmount() : int {
        return 5;
    }

    private function checkDaylightBurning() : void {
        if($this->fireTickCooldown > 0){ $this->fireTickCooldown--; return; }
        $time = $this->getWorld()->getTimeOfDay();
        if($time < 12000 && !$this->isUnderwater()){
            $highestY = $this->getWorld()->getHighestBlockAt((int)$this->location->x, (int)$this->location->z);
            if($highestY !== null && $this->location->y >= $highestY){
                $this->setOnFire(3);
                $this->attack(new EntityDamageEvent($this, EntityDamageEvent::CAUSE_FIRE_TICK, 1));
                $this->fireTickCooldown = 20; 
            }
        }
    }

    private function shootArrow() : void {
        if($this->target === null) return;
        $from = $this->getEyePos();
        $targetPos = $this->target->getEyePos();
        $dist = $from->distance($targetPos);
        
        $diffX = $targetPos->x - $from->x;
        $diffY = $targetPos->y - $from->y + ($dist * 0.07); 
        $diffZ = $targetPos->z - $from->z;

        $arrow = new ArrowEntity(Location::fromObject($from, $this->getWorld(), $this->location->yaw, $this->location->pitch), $this, $dist > 15);
        $arrow->setMotion((new Vector3($diffX, $diffY, $diffZ))->normalize()->multiply(2.0));

        $ev = new EntityShootBowEvent($this, $this->heldItem, $arrow, 2.0);
        $ev->call();
        if(!$ev->isCancelled()){
            $arrow->spawnToAll();
            $this->getWorld()->addSound($this->location, new BowShootSound());
            $this->broadcastAnimation(new ArmSwingAnimation($this));
        } else {
            $arrow->flagForDespawn();
        }
    }

    private function findNearestPlayer() : void {
        foreach($this->getWorld()->getPlayers() as $player){
            if($player->isAlive() && !$player->isCreative()){
                if($this->location->distanceSquared($player->getPosition()) < 300){
                    if($this->canSee($player)){ $this->target = $player; break; }
                }
            }
        }
    }

    private function canSee(Entity $entity) : bool {
        $start = $this->getEyePos();
        $end = $entity->getEyePos();
        $dir = $end->subtractVector($start)->normalize();
        for($i = 0; $i < $start->distance($end); $i += 0.8){
            $pos = $start->addVector($dir->multiply($i));
            if($this->getWorld()->getBlockAt((int)$pos->x, (int)$pos->y, (int)$pos->z)->isSolid()) return false;
        }
        return true;
    }

    protected function sendSpawnPacket(Player $player) : void {
        parent::sendSpawnPacket($player);
        $player->getNetworkSession()->sendDataPacket(MobEquipmentPacket::create($this->getId(), ItemStackWrapper::legacy($player->getNetworkSession()->getTypeConverter()->coreItemStackToNet($this->heldItem)), 0, 0, ContainerIds::INVENTORY));
    }
}