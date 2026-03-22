<?php
declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use function atan2;
use function mt_rand;
use function sqrt;
use function abs;
use function rad2deg;
use function deg2rad;
use function sin;
use function cos;
use function floor;

class Zombie extends Living {
    private ?Player $target = null;
    private int $attackCooldown = 0;
    private int $jumpCooldown = 0;
    private int $idleTime = 0;
    private ?Vector3 $wanderTarget = null;
    private int $fireTickCooldown = 0;
    private int $obstacleAvoidCooldown = 0;
    private float $smoothMotionX = 0.0;
    private float $smoothMotionZ = 0.0;

    public static function getNetworkTypeId() : string {
        return EntityIds::ZOMBIE;
    }

    protected function getInitialSizeInfo() : EntitySizeInfo {
        return new EntitySizeInfo(1.9, 0.6);
    }

    public function getName() : string {
        return "Zombie";
    }

    protected function initEntity(CompoundTag $nbt) : void {
        parent::initEntity($nbt);
        $this->setMaxHealth(20);
        $this->setHealth(20);
        $this->setStepHeight(1.0);
        $this->setCanSaveWithChunk(false);
    }

    protected function entityBaseTick(int $tickDiff = 1) : bool {
        $hasUpdate = parent::entityBaseTick($tickDiff);
        if(!$this->isAlive()) return $hasUpdate;

        // 1. Дневное горение
        $this->checkDaylightBurning();

        $this->validateTarget();
        if($this->obstacleAvoidCooldown > 0) $this->obstacleAvoidCooldown -= $tickDiff;

        if($this->target === null){
            $this->findNearestPlayer();
            $this->wanderAI();
        } else {
            $this->combatAI();
        }

        $this->handleVanillaJump();

        if($this->attackCooldown > 0) $this->attackCooldown -= $tickDiff;

        return true;
    }

    /**
     * Прыжок только для препятствий выше 1 блока. На 1 блок зомби забирается за счёт stepHeight=1.0.
     */
    private function handleVanillaJump() : void {
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

        if(!$blockFoot->isSolid() || $blockHead->isSolid() || $blockAbove->isSolid()) return;

        $obstacleTopY = $blockFoot->getPosition()->y + 1;
        $obstacleHeight = $obstacleTopY - $this->location->y;
        if($obstacleHeight <= 1.0) return;

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

        if($distSq <= 1.6) {
            $this->smoothMotionX *= 0.6;
            $this->smoothMotionZ *= 0.6;
            $this->motion->x = $this->smoothMotionX;
            $this->motion->z = $this->smoothMotionZ;
            $this->attackTarget();
            return;
        }

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
    }

    private function attackTarget() : void {
        if($this->attackCooldown <= 0 && $this->target !== null){
            // Анимация взмаха рук
            $this->broadcastAnimation(new ArmSwingAnimation($this));
            
            $ev = new EntityDamageByEntityEvent($this, $this->target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 3);
            $this->target->attack($ev);
            
            $this->attackCooldown = 20; // 1 удар в секунду
        }
    }

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

    private function getAvoidanceDirection(float $dirX, float $dirZ) : ?Vector3 {
        $len = sqrt($dirX * $dirX + $dirZ * $dirZ);
        if($len < 0.01) return null;
        $dirX /= $len;
        $dirZ /= $len;
        $leftX = -$dirZ;
        $leftZ = $dirX;
        $rightX = $dirZ;
        $rightZ = -$dirX;
        if(!$this->hasObstacleInFront($leftX, $leftZ, 0.9)) return new Vector3($leftX, 0, $leftZ);
        if(!$this->hasObstacleInFront($rightX, $rightZ, 0.9)) return new Vector3($rightX, 0, $rightZ);
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

    private function validateTarget() : void {
        if($this->target !== null){
            if(!$this->target->isAlive() || $this->target->isClosed() || $this->target->isCreative() || $this->location->distanceSquared($this->target->getPosition()) > 400){
                $this->target = null;
            }
        }
    }

    private function findNearestPlayer() : void {
        foreach($this->getWorld()->getPlayers() as $player){
            if($player->isAlive() && !$player->isCreative()){
                if($this->location->distanceSquared($player->getPosition()) < 256){
                    $this->target = $player;
                    break;
                }
            }
        }
    }

    public function getDrops() : array {
        $drops = [VanillaItems::ROTTEN_FLESH()->setCount(mt_rand(1, 2))];
        if(mt_rand(0, 100) < 5) $drops[] = VanillaItems::IRON_INGOT();
        return $drops;
    }

    public function getXpDropAmount() : int {
        return 5;
    }
}