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
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneWire;
use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\plugin\Plugin;
use pocketmine\world\Position;
use pocketmine\world\World;
use SplQueue;
use vape\pmredstone\config\RedstoneConfig;

final class RedstoneEngine {

    /**
     * Sparse power map, only entries with power over 0 are stored
     * Unset when power drops to 0 to prevent unbounded growth
     *
     * @var array<int, array<string, int>> worldId => posKey => powerLevel (1-15)
     */
    private array $powerMap = [];

    /** @var array<int, array<string, true>> worldId => posKey => in-queue flag */
    private array $dirtySet = [];

    /** @var array<int, SplQueue> worldId => queue of packed "x:y:z" strings */
    private array $dirtyQueue = [];

    private PistonEngine $pistonEngine;
    private PositionRegistry $registry;

    public function __construct(
        private readonly Plugin $plugin,
        private readonly RedstoneConfig $cfg
    ) {
        $this->pistonEngine = new PistonEngine($this, $cfg);
        $this->registry     = new PositionRegistry();
    }

    public function getPlugin(): Plugin {
        return $this->plugin;
    }

    public function getConfig(): RedstoneConfig {
        return $this->cfg;
    }

    public function getPistonEngine(): PistonEngine {
        return $this->pistonEngine;
    }

    public function getRegistry(): PositionRegistry {
        return $this->registry;
    }

    public function notifyChange(Position $pos): void {
        $world = $pos->getWorld();
        if ($this->cfg->isWorldDisabled($world->getFolderName())) {
            return;
        }

        $wid = $world->getId();
        $x   = (int) $pos->x;
        $y   = (int) $pos->y;
        $z   = (int) $pos->z;

        $this->enqueue($wid, $x, $y, $z);

        foreach (Facing::ALL as $face) {
            [$dx, $dy, $dz] = Facing::OFFSET[$face];
            $this->enqueue($wid, $x + $dx, $y + $dy, $z + $dz);
        }
    }

    private function enqueue(int $wid, int $x, int $y, int $z): void {
        $key = "$x:$y:$z";

        if (isset($this->dirtySet[$wid][$key])) {
            return;
        }

        if (!isset($this->dirtyQueue[$wid])) {
            $this->dirtyQueue[$wid] = new SplQueue();
        }

        if ($this->dirtyQueue[$wid]->count() >= $this->cfg->getMaxQueueSize()) {
            return;
        }

        $this->dirtySet[$wid][$key] = true;
        $this->dirtyQueue[$wid]->enqueue($key);
    }

    public function tick(): void {
        $wm     = $this->plugin->getServer()->getWorldManager();
        $budget = $this->cfg->getMaxUpdateBudget();

        foreach ($this->dirtyQueue as $wid => $queue) {
            if ($budget <= 0) {
                break;
            }

            $world = $wm->getWorld($wid);

            if ($world === null) {
                $this->invalidateWorld($wid);
                continue;
            }

            while (!$queue->isEmpty() && $budget-- > 0) {
                $key  = $queue->dequeue();
                unset($this->dirtySet[$wid][$key]);

                [$x, $y, $z] = $this->decodeKey($key);

                if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                    continue;
                }

                $block     = $world->getBlockAt($x, $y, $z);
                $newPower  = SignalPropagator::calculatePowerAt($this, $world, $block, $x, $y, $z);
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
                        $block->getName(), $x, $y, $z, $prevPower, $newPower
                    ));
                }

                $pos = new Position($x, $y, $z, $world);
                $this->applyPowerToBlock($world, $block, $pos, $prevPower, $newPower);

                foreach (Facing::ALL as $face) {
                    [$dx, $dy, $dz] = Facing::OFFSET[$face];
                    $this->enqueue($wid, $x + $dx, $y + $dy, $z + $dz);
                }
            }
        }
    }

    private function applyPowerToBlock(World $world, Block $block, Position $pos, int $prev, int $now): void {
        $wasPowered = $prev > 0;
        $isPowered  = $now > 0;

        if ($block instanceof RedstoneWire && $block instanceof AnalogRedstoneSignalEmitter) {
            $block->setSignalStrength($now);
            $world->setBlock($pos, $block);
            return;
        }

        if ($block instanceof RedstoneLamp && $block instanceof PoweredByRedstone) {
            if ($wasPowered !== $isPowered) {
                $block->setPowered($isPowered);
                $world->setBlock($pos, $block);
            }
            return;
        }

        if ($block instanceof RedstoneOre) {
            return;
        }

        if ($block instanceof RedstoneRepeater && $block instanceof PoweredByRedstone) {
            if ($wasPowered !== $isPowered) {
                $block->setPowered($isPowered);
                $world->setBlock($pos, $block);
            }
            return;
        }

        if ($block instanceof RedstoneComparator && $block instanceof PoweredByRedstone) {
            if ($wasPowered !== $isPowered) {
                $block->setPowered($isPowered);
                $world->setBlock($pos, $block);
            }
            return;
        }

        if ($this->cfg->isPistonsEnabled() && !$this->cfg->isPistonWorldDisabled($world->getFolderName())) {
            $this->pistonEngine->onPowerChange($block, $pos, $isPowered, $wasPowered);
        }
    }

    public function getStoredPower(World $world, int $x, int $y, int $z): int {
        return $this->powerMap[$world->getId()]["$x:$y:$z"] ?? 0;
    }

    public function getPowerMapSize(): int {
        $total = 0;
        foreach ($this->powerMap as $map) {
            $total += count($map);
        }
        return $total;
    }

    public function getDirtyQueueSize(): int {
        $total = 0;
        foreach ($this->dirtyQueue as $queue) {
            $total += $queue->count();
        }
        return $total;
    }

    public function invalidateWorld(int $wid): void {
        unset(
            $this->powerMap[$wid],
            $this->dirtySet[$wid],
            $this->dirtyQueue[$wid]
        );
        $this->registry->invalidateWorld($wid);
    }

    public function shutdown(): void {
        $this->powerMap   = [];
        $this->dirtySet   = [];
        $this->dirtyQueue = [];
    }

    /** @return array{int, int, int} */
    private function decodeKey(string $key): array {
        $parts = explode(":", $key);
        return [(int) $parts[0], (int) $parts[1], (int) $parts[2]];
    }
}
