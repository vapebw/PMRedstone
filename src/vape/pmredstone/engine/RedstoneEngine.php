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
use pocketmine\block\RedstoneLamp;
use pocketmine\block\RedstoneOre;
use pocketmine\block\Door;
use pocketmine\block\FenceGate;
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\block\Trapdoor;
use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\math\Facing;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\Task;
use pocketmine\world\Position;
use pocketmine\world\World;
use SplQueue;
use vape\pmredstone\config\RedstoneConfig;

final class RedstoneEngine
{

    /**
     * @var array<int, array<string, int>> worldId => posKey => powerLevel (1-15)
     */
    private array $powerMap = [];

    /** @var array<int, array<string, true>> worldId => posKey => in-queue flag */
    private array $dirtySet = [];

    /** @var array<int, SplQueue> worldId => queue of packed "x:y:z" strings */
    private array $dirtyQueue = [];

    /** @var array<int, SplQueue> worldId => queue of updates for the next tick */
    private array $delayedQueue = [];

    /** @var array<int, array<string, int>> */
    private array $pendingRepeaterUpdates = [];

    /** @var array<int, array<string, int>> */
    private array $pendingComparatorUpdates = [];

    private PistonEngine $pistonEngine;
    private PositionRegistry $registry;

    public function __construct(
        private readonly Plugin $plugin,
        private readonly RedstoneConfig $cfg
    ) {
        $this->pistonEngine = new PistonEngine($this, $cfg);
        $this->registry = new PositionRegistry();
    }

    public function getPlugin(): Plugin
    {
        return $this->plugin;
    }

    public function getConfig(): RedstoneConfig
    {
        return $this->cfg;
    }

    public function getPistonEngine(): PistonEngine
    {
        return $this->pistonEngine;
    }

    public function getRegistry(): PositionRegistry
    {
        return $this->registry;
    }

    public function notifyChange(Position $pos): void
    {
        $world = $pos->getWorld();
        if ($this->cfg->isWorldDisabled($world->getFolderName())) {
            return;
        }

        $wid = $world->getId();
        $x = (int) $pos->x;
        $y = (int) $pos->y;
        $z = (int) $pos->z;

        $this->enqueue($wid, $x, $y, $z);

        foreach (Facing::ALL as $face) {
            [$dx, $dy, $dz] = Facing::OFFSET[$face];
            $this->enqueue($wid, $x + $dx, $y + $dy, $z + $dz);
        }
    }

    private function enqueue(int $wid, int $x, int $y, int $z, bool $delayed = false): void
    {
        $key = "$x:$y:$z";

        if (isset($this->dirtySet[$wid][$key])) {
            return;
        }

        $targetQueue = $delayed ? "delayedQueue" : "dirtyQueue";

        if (!isset($this->{$targetQueue}[$wid])) {
            $this->{$targetQueue}[$wid] = new SplQueue();
        }

        if ($this->{$targetQueue}[$wid]->count() >= $this->cfg->getMaxQueueSize()) {
            return;
        }

        $this->dirtySet[$wid][$key] = true;
        $this->{$targetQueue}[$wid]->enqueue($key);
    }

    public function tick(): void
    {
        $wm = $this->plugin->getServer()->getWorldManager();
        $budget = $this->cfg->getMaxUpdateBudget() * 2;

        foreach ($this->delayedQueue as $wid => $queue) {
            while (!$queue->isEmpty()) {
                $key = $queue->dequeue();
                unset($this->dirtySet[$wid][$key]);
                [$x, $y, $z] = $this->decodeKey($key);
                $this->enqueue($wid, $x, $y, $z);
            }
            if ($queue->isEmpty()) {
                unset($this->delayedQueue[$wid]);
            }
        }

        $worlds = array_keys($this->dirtyQueue);
        if (count($worlds) > 0) {
            $worldBudget = (int) ($budget / count($worlds));
            foreach ($this->dirtyQueue as $wid => $queue) {
                $world = $wm->getWorld($wid);
                if ($world === null) {
                    $this->invalidateWorld($wid);
                    continue;
                }

                $processed = 0;
                while (!$queue->isEmpty() && $processed < $worldBudget && $budget > 0) {
                    $processed++;
                    $budget--;
                    $key = $queue->dequeue();
                    unset($this->dirtySet[$wid][$key]);

                    [$x, $y, $z] = $this->decodeKey($key);

                    if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                        continue;
                    }

                    $block = $world->getBlockAt($x, $y, $z);

                    if ($block instanceof RedstoneRepeater && $this->cfg->isRepeaterEnabled()) {
                        $this->updateRepeater($world, $block, $x, $y, $z);
                        continue;
                    }

                    if ($block instanceof RedstoneComparator && $this->cfg->isComparatorEnabled()) {
                        $this->updateComparator($world, $block, $x, $y, $z);
                        continue;
                    }

                    $newPower = SignalPropagator::calculatePowerAt($this, $world, $block, $x, $y, $z);
                    $prevPower = $this->powerMap[$wid][$key] ?? 0;

                    if ($newPower === $prevPower) {
                        continue;
                    }

                    if ($newPower === 0) {
                        unset($this->powerMap[$wid][$key]);
                        if (isset($this->powerMap[$wid]) && count($this->powerMap[$wid]) === 0) {
                            unset($this->powerMap[$wid]);
                        }
                    } else {
                        $this->powerMap[$wid][$key] = $newPower;
                    }

                    if ($this->cfg->isDebugPowerChanges()) {
                        $this->plugin->getLogger()->debug(sprintf(
                            "[PowerChange] %s @ %d,%d,%d : %d -> %d",
                            $block->getName(),
                            $x,
                            $y,
                            $z,
                            $prevPower,
                            $newPower
                        ));
                    }

                    $pos = new Position($x, $y, $z, $world);
                    $this->applyPowerToBlock($world, $block, $pos, $prevPower, $newPower);

                    foreach (Facing::ALL as $face) {
                        [$dx, $dy, $dz] = Facing::OFFSET[$face];
                        $this->enqueue($wid, $x + $dx, $y + $dy, $z + $dz);
                    }
                }

                if ($queue->isEmpty()) {
                    unset($this->dirtyQueue[$wid]);
                }
            }
        }
    }

    private function applyPowerToBlock(World $world, Block $block, Position $pos, int $prev, int $now): void
    {
        $wasPowered = $prev > 0;
        $isPowered = $now > 0;

        if ($block instanceof RedstoneWire && $block instanceof AnalogRedstoneSignalEmitter) {
            $world->setBlock($pos, $block->setOutputSignalStrength($now));
            return;
        }

        if ($block instanceof RedstoneLamp && $block instanceof PoweredByRedstone) {
            if ($wasPowered !== $isPowered) {
                $world->setBlock($pos, $block->setPowered($isPowered));
            }
            return;
        }

        if ($block instanceof RedstoneOre) {
            return;
        }

        if ($block instanceof RedstoneRepeater || $block instanceof RedstoneComparator || $block instanceof RedstoneTorch) {
            if ($wasPowered !== $isPowered) {
                if ($block instanceof PoweredByRedstone) {
                    $block = $block->setPowered($isPowered);
                }
                $world->setBlock($pos, $block);
                foreach (Facing::ALL as $face) {
                    $side = $pos->getSide($face);
                    $this->enqueue($world->getId(), $side->getFloorX(), $side->getFloorY(), $side->getFloorZ(), true);
                }
            }
            return;
        }

        if ($block instanceof Door || $block instanceof Trapdoor || $block instanceof FenceGate) {
            if ($wasPowered !== $isPowered) {
                $world->setBlock($pos, $block->setOpen($isPowered));
                if ($block instanceof Door) {
                    $other = $pos->getSide($block->isTop() ? Facing::DOWN : Facing::UP);
                    $otherBlock = $world->getBlock($other);
                    if ($otherBlock instanceof Door) {
                        $world->setBlock($other, $otherBlock->setOpen($isPowered));
                    }
                }
            }
            return;
        }

        if ($this->cfg->isPistonsEnabled() && !$this->cfg->isPistonWorldDisabled($world->getFolderName())) {
            $this->pistonEngine->onPowerChange($block, $pos, $isPowered, $wasPowered);
        }
    }

    private function updateRepeater(World $world, RedstoneRepeater $block, int $x, int $y, int $z): void
    {
        $desiredPowered = SignalPropagator::calculateRepeaterInput($this, $world, $block, $x, $y, $z) > 0;
        $currentPower = $block->isPowered() ? 15 : 0;
        $targetPower = $desiredPowered ? 15 : 0;

        $wid = $world->getId();
        $key = "$x:$y:$z";

        if ($currentPower === $targetPower) {
            $this->syncStoredPower($wid, $key, $targetPower);
            unset($this->pendingRepeaterUpdates[$wid][$key]);
            if (isset($this->pendingRepeaterUpdates[$wid]) && count($this->pendingRepeaterUpdates[$wid]) === 0) {
                unset($this->pendingRepeaterUpdates[$wid]);
            }
            return;
        }

        if (($this->pendingRepeaterUpdates[$wid][$key] ?? null) === $targetPower) {
            return;
        }

        $this->pendingRepeaterUpdates[$wid][$key] = $targetPower;
        $this->plugin->getScheduler()->scheduleDelayedTask(
            new class($this, new Position($x, $y, $z, $world), $targetPower) extends Task {
                public function __construct(
                    private readonly RedstoneEngine $engine,
                    private readonly Position $pos,
                    private readonly int $targetPower
                ) {}

                public function onRun(): void
                {
                    $this->engine->applyScheduledRepeaterUpdate($this->pos, $this->targetPower);
                }
            },
            $block->getDelay() * 2
        );
    }

    private function updateComparator(World $world, RedstoneComparator $block, int $x, int $y, int $z): void
    {
        $targetPower = SignalPropagator::calculateComparatorOutput($this, $world, $block, $x, $y, $z);
        $currentPower = $block->getOutputSignalStrength();

        $wid = $world->getId();
        $key = "$x:$y:$z";

        if ($currentPower === $targetPower) {
            $this->syncStoredPower($wid, $key, $targetPower);
            unset($this->pendingComparatorUpdates[$wid][$key]);
            if (isset($this->pendingComparatorUpdates[$wid]) && count($this->pendingComparatorUpdates[$wid]) === 0) {
                unset($this->pendingComparatorUpdates[$wid]);
            }
            return;
        }

        if (($this->pendingComparatorUpdates[$wid][$key] ?? null) === $targetPower) {
            return;
        }

        $this->pendingComparatorUpdates[$wid][$key] = $targetPower;
        $this->plugin->getScheduler()->scheduleDelayedTask(
            new class($this, new Position($x, $y, $z, $world), $targetPower) extends Task {
                public function __construct(
                    private readonly RedstoneEngine $engine,
                    private readonly Position $pos,
                    private readonly int $targetPower
                ) {}

                public function onRun(): void
                {
                    $this->engine->applyScheduledComparatorUpdate($this->pos, $this->targetPower);
                }
            },
            SignalPropagator::getComparatorDelayTicks()
        );
    }

    public function applyScheduledRepeaterUpdate(Position $pos, int $targetPower): void
    {
        $world = $pos->getWorld();
        $wid = $world->getId();
        $key = "{$pos->getFloorX()}:{$pos->getFloorY()}:{$pos->getFloorZ()}";

        if (($this->pendingRepeaterUpdates[$wid][$key] ?? null) !== $targetPower) {
            return;
        }

        unset($this->pendingRepeaterUpdates[$wid][$key]);
        if (isset($this->pendingRepeaterUpdates[$wid]) && count($this->pendingRepeaterUpdates[$wid]) === 0) {
            unset($this->pendingRepeaterUpdates[$wid]);
        }

        if (!$world->isChunkLoaded($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4)) {
            return;
        }

        $block = $world->getBlock($pos);
        if (!$block instanceof RedstoneRepeater || !$block instanceof PoweredByRedstone) {
            return;
        }

        $desiredPowered = $targetPower > 0;
        if ($block->isPowered() === $desiredPowered) {
            $this->syncStoredPower($wid, $key, $targetPower);
            return;
        }

        $block = $block->setPowered($desiredPowered);
        $world->setBlock($pos, $block);
        $this->syncStoredPower($wid, $key, $targetPower);
        $this->notifyChange($pos);
    }

    public function applyScheduledComparatorUpdate(Position $pos, int $targetPower): void
    {
        $world = $pos->getWorld();
        $wid = $world->getId();
        $key = "{$pos->getFloorX()}:{$pos->getFloorY()}:{$pos->getFloorZ()}";

        if (($this->pendingComparatorUpdates[$wid][$key] ?? null) !== $targetPower) {
            return;
        }

        unset($this->pendingComparatorUpdates[$wid][$key]);
        if (isset($this->pendingComparatorUpdates[$wid]) && count($this->pendingComparatorUpdates[$wid]) === 0) {
            unset($this->pendingComparatorUpdates[$wid]);
        }

        if (!$world->isChunkLoaded($pos->getFloorX() >> 4, $pos->getFloorZ() >> 4)) {
            return;
        }

        $block = $world->getBlock($pos);
        if (
            !$block instanceof RedstoneComparator ||
            !$block instanceof PoweredByRedstone ||
            !$block instanceof AnalogRedstoneSignalEmitter
        ) {
            return;
        }

        $desiredPowered = $targetPower > 0;
        if ($block->isPowered() === $desiredPowered && $block->getOutputSignalStrength() === $targetPower) {
            $this->syncStoredPower($wid, $key, $targetPower);
            return;
        }

        $block = $block->setPowered($desiredPowered);
        $block = $block->setOutputSignalStrength($targetPower);
        $world->setBlock($pos, $block);
        $this->syncStoredPower($wid, $key, $targetPower);
        $this->notifyChange($pos);
    }

    private function syncStoredPower(int $wid, string $key, int $power): void
    {
        if ($power <= 0) {
            unset($this->powerMap[$wid][$key]);
            if (isset($this->powerMap[$wid]) && count($this->powerMap[$wid]) === 0) {
                unset($this->powerMap[$wid]);
            }
            return;
        }

        $this->powerMap[$wid][$key] = $power;
    }

    public function getStoredPower(World $world, int $x, int $y, int $z): int
    {
        $wid = $world->getId();
        $key = "$x:$y:$z";
        if (isset($this->powerMap[$wid][$key])) {
            return $this->powerMap[$wid][$key];
        }

        $block = $world->getBlockAt($x, $y, $z);
        if ($block instanceof RedstoneWire && $block instanceof AnalogRedstoneSignalEmitter) {
            return $block->getOutputSignalStrength();
        }

        return 0;
    }

    public function getPowerMapSize(): int
    {
        $total = 0;
        foreach ($this->powerMap as $map) {
            $total += count($map);
        }
        return $total;
    }

    public function getDirtyQueueSize(): int
    {
        $total = 0;
        foreach ($this->dirtyQueue as $queue) {
            $total += $queue->count();
        }
        return $total;
    }

    public function invalidateWorld(int $wid): void
    {
        unset(
            $this->powerMap[$wid],
            $this->dirtySet[$wid],
            $this->dirtyQueue[$wid],
            $this->delayedQueue[$wid],
            $this->pendingRepeaterUpdates[$wid],
            $this->pendingComparatorUpdates[$wid]
        );
        $this->registry->invalidateWorld($wid);
    }

    public function shutdown(): void
    {
        $this->powerMap = [];
        $this->dirtySet = [];
        $this->dirtyQueue = [];
        $this->delayedQueue = [];
        $this->pendingRepeaterUpdates = [];
        $this->pendingComparatorUpdates = [];
    }

    /** @return array{int, int, int} */
    private function decodeKey(string $key): array
    {
        $parts = explode(":", $key);
        return [(int) $parts[0], (int) $parts[1], (int) $parts[2]];
    }
}
