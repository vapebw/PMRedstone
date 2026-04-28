<?php

declare(strict_types=1);

namespace vape\pmredstone\engine;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\world\Position;
use pocketmine\world\World;
use vape\pmredstone\block\PistonBlock;
use vape\pmredstone\block\PistonBlockRegistry;
use vape\pmredstone\block\PistonHeadBlock;
use vape\pmredstone\tile\PistonArmTile;
use vape\pmredstone\block\StickyPistonHeadBlock;
use vape\pmredstone\config\RedstoneConfig;
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

        if ($this->cfg->isPistonWorldDisabled($pos->getWorld()->getFolderName())) {
            return;
        }

        $this->reconcilePiston($pos, $block, $isPowered);
    }

    /**
     * returns an ordered list of positions to push, starting clo(s)est to piston
     * returns null if push is blocked.
     *
     * @return Position[]|null
     */
    public function collectPushChain(World $world, Position $pistonPos, int $facing): ?array {
        [$dx, $dy, $dz] = Facing::OFFSET[$facing];
        $chain = [];
        $maxPush = $this->cfg->getPistonMaxPush();

        for ($i = 1; $i <= $maxPush + 1; $i++) {
            $tx = $pistonPos->getFloorX() + ($dx * $i);
            $ty = $pistonPos->getFloorY() + ($dy * $i);
            $tz = $pistonPos->getFloorZ() + ($dz * $i);

            if (
                !$world->isInWorld($tx, $ty, $tz) ||
                !$world->isChunkLoaded($tx >> 4, $tz >> 4)
            ) {
                return null;
            }

            $target = $world->getBlockAt($tx, $ty, $tz);
            if ($target->getTypeId() === VanillaBlocks::AIR()->getTypeId()) {
                return $chain;
            }

            if (!BlockUtil::isMovable($target) || count($chain) >= $maxPush) {
                return null;
            }

            $chain[] = new Position($tx, $ty, $tz, $world);
        }

        return null;
    }

    public function executePush(World $world, Position $pistonPos, PistonBlock $piston): void {
        if ($this->isHeadPresent($world, $pistonPos, $piston)) {
            return;
        }

        $facing = $piston->getFacing();
        $chain = $this->collectPushChain($world, $pistonPos, $facing);
        if ($chain === null) {
            return;
        }

        [$dx, $dy, $dz] = Facing::OFFSET[$facing];

        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $from = $chain[$i];
            $to = new Position(
                $from->getFloorX() + $dx,
                $from->getFloorY() + $dy,
                $from->getFloorZ() + $dz,
                $world
            );
            $movingBlock = $world->getBlock($from);
            $world->setBlock($to, $movingBlock);
            $world->setBlock($from, VanillaBlocks::AIR());

            $this->engine->notifyChange($to);
            $this->engine->notifyChange($from);
        }

        $sticky = $piston->isSticky();
        $world->setBlock($pistonPos, (clone $piston)->setExtended(true));
        $this->updatePistonTile($world, $pistonPos, true, $sticky);
        $headPos = $pistonPos->getSide($facing);
        $visualBlock = $this->placeHeadVisual($world, $headPos, $sticky, $facing);
        $this->updatePistonTile($world, $headPos, true, $sticky);
        $this->engine->notifyChange($pistonPos);
        $this->engine->notifyChange($headPos);

        if ($this->cfg->isDebugPiston()) {
            $this->engine->getPlugin()->getLogger()->debug(sprintf(
                "[Piston][EXTEND] facing=%s headPos=%d,%d,%d",
                PistonBlock::facingName($facing),
                $headPos->getFloorX(),
                $headPos->getFloorY(),
                $headPos->getFloorZ()
            ));
        }
    }

    public function executeRetract(World $world, Position $pistonPos, PistonBlock $piston): void {
        if (!$this->isHeadPresent($world, $pistonPos, $piston)) {
            return;
        }

        $facing = $piston->getFacing();
        [$dx, $dy, $dz] = Facing::OFFSET[$facing];
        $headPos = $pistonPos->getSide($facing);
        $world->setBlock($headPos, VanillaBlocks::AIR());

        $world->setBlock($pistonPos, (clone $piston)->setExtended(false));
        $this->updatePistonTile($world, $pistonPos, false, $piston->isSticky());

        $this->engine->notifyChange($headPos);
        $this->engine->notifyChange($pistonPos);

        if ($piston->isSticky()) {
            $pullPos = new Position(
                $pistonPos->getFloorX() + ($dx * 2),
                $pistonPos->getFloorY() + ($dy * 2),
                $pistonPos->getFloorZ() + ($dz * 2),
                $world
            );

            if (
                $world->isInWorld($pullPos->getFloorX(), $pullPos->getFloorY(), $pullPos->getFloorZ()) &&
                $world->isChunkLoaded($pullPos->getFloorX() >> 4, $pullPos->getFloorZ() >> 4)
            ) {
                $target = $world->getBlock($pullPos);
                if (
                    $target->getTypeId() !== VanillaBlocks::AIR()->getTypeId() &&
                    BlockUtil::isMovable($target)
                ) {
                    $world->setBlock($headPos, $target);
                    $world->setBlock($pullPos, VanillaBlocks::AIR());
                    $this->engine->notifyChange($headPos);
                    $this->engine->notifyChange($pullPos);
                }
            }
        }

        if ($this->cfg->isDebugPiston()) {
            $this->engine->getPlugin()->getLogger()->debug(sprintf(
                "[Piston][RETRACT] facing=%s headExists=true",
                PistonBlock::facingName($facing)
            ));
        }
    }

    private function reconcilePiston(Position $pos, Block $block, ?bool $powered = null): void {
        $world = $pos->getWorld();
        if (
            !$world->isInWorld($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ()) ||
            !$world->isChunkLoaded($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4) ||
            !$block instanceof PistonBlock
        ) {
            return;
        }

        $powered ??= SignalPropagator::calculatePowerAt(
            $this->engine,
            $world,
            $block,
            $pos->getFloorX(),
            $pos->getFloorY(),
            $pos->getFloorZ()
        ) > 0;

        $headExists = $this->isHeadPresent($world, $pos, $block);

        if ($powered) {
            if ($headExists) {
                $this->ensureHeadState($world, $pos, $block, true);
                return;
            }

            $this->executePush($world, $pos, $block);
            return;
        }

        if ($headExists) {
            $this->executeRetract($world, $pos, $block);
            return;
        }

        if ($this->cfg->isDebugPiston()) {
            $this->engine->getPlugin()->getLogger()->debug(sprintf(
                "[Piston][RETRACT] facing=%s headExists=false",
                PistonBlock::facingName($block->getFacing())
            ));
        }

        $this->ensureHeadState($world, $pos, $block, false);
    }

    private function isHeadPresent(World $world, Position $pistonPos, PistonBlock $piston): bool {
        $headPos = $pistonPos->getSide($piston->getFacing());
        $front = $world->getBlock($headPos);
        $headFacing = BlockUtil::getPistonFacing($front);
        
        if ($this->cfg->isDebugPiston()) {
            $this->engine->getPlugin()->getLogger()->debug(sprintf(
                "[Piston][CHECK] baseFacing=%s headFacing=%s",
                PistonBlock::facingName($piston->getFacing()),
                PistonBlock::facingName($headFacing)
            ));
        }

        return (
            ($piston->isSticky() && $front instanceof StickyPistonHeadBlock) ||
            (!$piston->isSticky() && $front instanceof PistonHeadBlock && !$front instanceof StickyPistonHeadBlock)
        ) && $headFacing === $piston->getFacing();
    }

    private function ensureHeadState(World $world, Position $pistonPos, PistonBlock $piston, bool $shouldExist): void {
        $headPos = $pistonPos->getSide($piston->getFacing());
        $front = $world->getBlock($headPos);
        $hasMatchingHead = (
            ($piston->isSticky() && $front instanceof StickyPistonHeadBlock) ||
            (!$piston->isSticky() && $front instanceof PistonHeadBlock && !$front instanceof StickyPistonHeadBlock)
        ) && BlockUtil::getPistonFacing($front) === $piston->getFacing();

        if ($shouldExist) {
            if (!$hasMatchingHead) {
                $this->placeHeadVisual($world, $headPos, $piston->isSticky(), $piston->getFacing());
                $this->updatePistonTile($world, $headPos, true, $piston->isSticky());
                $this->engine->notifyChange($headPos);
            }
            return;
        }

        if ($hasMatchingHead) {
            $world->setBlock($headPos, VanillaBlocks::AIR());
            $this->engine->notifyChange($headPos);
        }
    }

    private function createHeadBlock(bool $sticky, int $facing): Block {
        $head = $sticky ? PistonBlockRegistry::stickyPistonHead() : PistonBlockRegistry::pistonHead();
        return $head->setFacing($facing);
    }

    private function placeHeadVisual(World $world, Position $headPos, bool $sticky, int $facing): Block {
        $head = $this->createHeadBlock($sticky, $facing);
        $world->setBlock($headPos, $head);

        $placed = $world->getBlock($headPos);
        $validHead = BlockUtil::isPistonHead($placed) && BlockUtil::getPistonFacing($placed) === $facing;
        if ($validHead) {
            return $placed;
        }

        $fallback = PistonBlockRegistry::movingBlock();
        $world->setBlock($headPos, $fallback);

        if ($this->cfg->isDebugPiston()) {
            $this->engine->getPlugin()->getLogger()->warning(sprintf(
                "[Piston] Head fallback @ %d,%d,%d facing=%s placed=%s fallback=%s",
                $headPos->getFloorX(),
                $headPos->getFloorY(),
                $headPos->getFloorZ(),
                PistonBlock::facingName($facing),
                $placed->getName(),
                $fallback->getName()
            ));
        }

        return $fallback;
    }

    private function updatePistonTile(World $world, Position $pos, bool $extended, bool $sticky): void {
        $tile = $world->getTile($pos);
        if (!$tile instanceof PistonArmTile) {
            $tile = new PistonArmTile($world, $pos);
            $world->addTile($tile);
        }
        $tile->setSticky($sticky);
        if ($extended) {
            $tile->setState(2);
            $tile->setNewState(2);
            $tile->setProgress(1.0);
            $tile->setLastProgress(1.0);
        } else {
            $tile->setState(0);
            $tile->setNewState(0);
            $tile->setProgress(0.0);
            $tile->setLastProgress(0.0);
        }
    }
}
