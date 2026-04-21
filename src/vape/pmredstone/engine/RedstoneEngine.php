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

    /** @var array<int, array<string, int>> worldId => posKey => powerLevel (0-15) */
    private array $powerMap = [];

    /** @var array<int, array<string, true>> worldId => posKey => in-queue flag */
    private array $dirtySet = [];

    /** @var array<int, SplQueue<Vector3>> worldId => dirty position queue */
    private array $dirtyQueue = [];

    private PistonEngine $pistonEngine;

    public function __construct(
        private readonly Plugin $plugin,
        private readonly RedstoneConfig $cfg
    ) {
        $this->pistonEngine = new PistonEngine($this, $cfg);
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

    public function notifyChange(Position $pos): void {
        $world = $pos->getWorld();
        if ($this->cfg->isWorldDisabled($world->getFolderName())) {
            return;
        }

        $wid = $world->getId();
        $this->enqueue($wid, (int) $pos->x, (int) $pos->y, (int) $pos->z);

        foreach (Facing::ALL as $face) {
            [$dx, $dy, $dz] = Facing::OFFSET[$face];
            $this->enqueue($wid, (int) $pos->x + $dx, (int) $pos->y + $dy, (int) $pos->z + $dz);
        }
    }

    private function enqueue(int $wid, int $x, int $y, int $z): void {
        $key = $this->key($x, $y, $z);

        if (isset($this->dirtySet[$wid][$key])) {
            return;
        }

        if (!isset($this->dirtyQueue[$wid])) {
            $this->dirtyQueue[$wid] = new SplQueue();
            $this->dirtyQueue[$wid]->setIteratorMode(SplQueue::IT_MODE_DELETE);
        }

        if ($this->dirtyQueue[$wid]->count() >= $this->cfg->getMaxQueueSize()) {
            return;
        }

        $this->dirtySet[$wid][$key] = true;
        $this->dirtyQueue[$wid]->enqueue(new Vector3($x, $y, $z));
    }

    public function tick(): void {
        $wm = $this->plugin->getServer()->getWorldManager();

        foreach ($this->dirtyQueue as $wid => $queue) {
            $world = $wm->getWorld($wid);

            if ($world === null) {
                $this->invalidateWorld($wid);
                continue;
            }

            $budget = $this->cfg->getMaxUpdateBudget();

            while (!$queue->isEmpty() && $budget-- > 0) {
                /** @var Vector3 $vec */
                $vec = $queue->dequeue();
                $key = $this->key((int) $vec->x, (int) $vec->y, (int) $vec->z);
                unset($this->dirtySet[$wid][$key]);

                if (!$world->isChunkLoaded((int) $vec->x >> 4, (int) $vec->z >> 4)) {
                    continue;
                }

                $x = (int) $vec->x;
                $y = (int) $vec->y;
                $z = (int) $vec->z;
                $block = $world->getBlockAt($x, $y, $z);
                $newPower = SignalPropagator::calculatePowerAt($this, $world, $block, $vec);
                $prevPower = $this->powerMap[$wid][$key] ?? 0;

                if ($newPower === $prevPower) {
                    continue;
                }

                $this->powerMap[$wid][$key] = $newPower;

                if ($this->cfg->isDebugPowerChanges()) {
                    $this->plugin->getLogger()->debug(sprintf(
                        "[PowerChange] %s @ %d,%d,%d : %d → %d",
                        $block->getName(), $x, $y, $z, $prevPower, $newPower
                    ));
                }

                $this->applyPowerToBlock($world, $block, new Position($x, $y, $z, $world), $prevPower, $newPower);

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
        return $this->powerMap[$world->getId()][$this->key($x, $y, $z)] ?? 0;
    }

    public function invalidateWorld(int $wid): void {
        unset($this->powerMap[$wid], $this->dirtySet[$wid], $this->dirtyQueue[$wid]);
    }

    public function shutdown(): void {
        $this->powerMap  = [];
        $this->dirtySet  = [];
        $this->dirtyQueue = [];
    }

    private function key(int $x, int $y, int $z): string {
        return "$x:$y:$z";
    }
}
