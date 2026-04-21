<?php

/*
 * ██╗   ██╗ █████╗ ██████╗ ███████╗
 * ██║   ██║██╔══██╗██╔══██╗██╔════╝
 * ██║   ██║███████║██████╔╝█████╗
 * ╚██╗ ██╔╝██╔══██║██╔═══╝ ██╔══╝
 *  ╚████╔╝ ██║  ██║██║     ███████╗
 *   ╚═══╝  ╚═╝  ╚═╝╚═╝     ╚══════╝
 *
 * PMRedstone - Full redstone simulation engine for PocketMine-MP 5
 *
 * Free to use. Do NOT sell or redistribute this plugin for profit.
 * GitHub: https://github.com/vapebw
 */

declare(strict_types=1);

namespace vape\pmredstone\engine;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\world\Position;
use pocketmine\world\World;
use vape\pmredstone\config\RedstoneConfig;
use vape\pmredstone\scheduler\PistonMoveTask;
use vape\pmredstone\util\BlockUtil;

final class PistonEngine {

    public function __construct(
        private readonly RedstoneEngine $engine,
        private readonly RedstoneConfig $cfg
    ) {}

    public function onPowerChange(Block $block, Position $pos, bool $isPowered, bool $wasPowered): void {
        if (!BlockUtil::isPiston($block)) {
            return;
        }

        if ($isPowered === $wasPowered) {
            return;
        }

        $worldName = $pos->getWorld()->getFolderName();

        if ($this->cfg->isPistonWorldDisabled($worldName)) {
            return;
        }

        $facing = BlockUtil::getPistonFacing($block);
        $sticky = BlockUtil::isStickyPiston($block);

        if ($isPowered) {
            $this->schedulePush($pos, $facing, $sticky);
        } else {
            $this->scheduleRetract($pos, $facing, $sticky);
        }
    }

    private function schedulePush(Position $pos, int $facing, bool $sticky): void {
        $this->engine->getPlugin()->getScheduler()->scheduleDelayedTask(
            new PistonMoveTask($this->engine, $pos, $facing, $sticky, true),
            $this->cfg->getPistonPushDelay()
        );
    }

    private function scheduleRetract(Position $pos, int $facing, bool $sticky): void {
        if (!$sticky && !$this->cfg->isStickyRetract()) {
            return;
        }

        $this->engine->getPlugin()->getScheduler()->scheduleDelayedTask(
            new PistonMoveTask($this->engine, $pos, $facing, $sticky, false),
            $this->cfg->getPistonRetractDelay()
        );
    }

    /**
     * Returns an ordered list of positions to push, starting closest to piston.
     * Returns null if push is blocked.
     *
     * @return Position[]|null
     */
    public function collectPushChain(World $world, Position $pistonPos, int $facing): ?array {
        [$dx, $dy, $dz] = Facing::OFFSET[$facing];
        $chain    = [];
        $maxPush  = $this->cfg->getPistonMaxPush();

        for ($i = 1; $i <= $maxPush + 1; $i++) {
            $tx = (int) $pistonPos->x + ($dx * $i);
            $ty = (int) $pistonPos->y + ($dy * $i);
            $tz = (int) $pistonPos->z + ($dz * $i);

            if (!$world->isChunkLoaded($tx >> 4, $tz >> 4)) {
                return null;
            }

            $target = $world->getBlockAt($tx, $ty, $tz);

            if ($target->getTypeId() === VanillaBlocks::AIR()->getTypeId()) {
                return $chain;
            }

            if (!BlockUtil::isMovable($target)) {
                return null;
            }

            if (count($chain) >= $maxPush) {
                return null;
            }

            $chain[] = new Position($tx, $ty, $tz, $world);
        }

        return null;
    }

    public function executePush(World $world, Position $pistonPos, int $facing, bool $sticky): void {
        $chain = $this->collectPushChain($world, $pistonPos, $facing);

        if ($chain === null) {
            return;
        }

        [$dx, $dy, $dz] = Facing::OFFSET[$facing];

        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $from = $chain[$i];
            $to   = new Position(
                (int) $from->x + $dx,
                (int) $from->y + $dy,
                (int) $from->z + $dz,
                $world
            );
            $movingBlock = $world->getBlockAt((int) $from->x, (int) $from->y, (int) $from->z);
            $world->setBlock($to, $movingBlock);
            $world->setBlock($from, VanillaBlocks::AIR());

            $this->engine->notifyChange($to);
            $this->engine->notifyChange($from);
        }

        if ($this->cfg->isDebugPiston()) {
            $this->engine->getPlugin()->getLogger()->debug(sprintf(
                "[Piston] Push @ %d,%d,%d facing %d moved %d blocks",
                (int) $pistonPos->x,
                (int) $pistonPos->y,
                (int) $pistonPos->z,
                $facing,
                count($chain)
            ));
        }
    }

    public function executeRetract(World $world, Position $pistonPos, int $facing, bool $sticky): void {
        if (!$sticky) {
            return;
        }

        [$dx, $dy, $dz] = Facing::OFFSET[$facing];
        $pullX = (int) $pistonPos->x + ($dx * 2);
        $pullY = (int) $pistonPos->y + ($dy * 2);
        $pullZ = (int) $pistonPos->z + ($dz * 2);

        if (!$world->isChunkLoaded($pullX >> 4, $pullZ >> 4)) {
            return;
        }

        $target = $world->getBlockAt($pullX, $pullY, $pullZ);

        if ($target->getTypeId() === VanillaBlocks::AIR()->getTypeId()) {
            return;
        }

        if (!BlockUtil::isMovable($target)) {
            return;
        }

        $destX = (int) $pistonPos->x + $dx;
        $destY = (int) $pistonPos->y + $dy;
        $destZ = (int) $pistonPos->z + $dz;
        $dest  = new Position($destX, $destY, $destZ, $world);

        $world->setBlock($dest, $target);
        $world->setBlock(new Position($pullX, $pullY, $pullZ, $world), VanillaBlocks::AIR());

        $this->engine->notifyChange($dest);
        $this->engine->notifyChange(new Position($pullX, $pullY, $pullZ, $world));

        if ($this->cfg->isDebugPiston()) {
            $this->engine->getPlugin()->getLogger()->debug(sprintf(
                "[Piston] Retract @ %d,%d,%d pulled block from %d,%d,%d",
                (int) $pistonPos->x, (int) $pistonPos->y, (int) $pistonPos->z,
                $pullX, $pullY, $pullZ
            ));
        }
    }
}
