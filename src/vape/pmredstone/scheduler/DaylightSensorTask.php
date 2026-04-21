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

namespace vape\pmredstone\scheduler;

use pocketmine\block\DaylightSensor;
use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\scheduler\Task;
use pocketmine\world\World;
use vape\pmredstone\config\RedstoneConfig;
use vape\pmredstone\engine\RedstoneEngine;

final class DaylightSensorTask extends Task {

    private const NOON_TIME     = 6000;
    private const DAY_DURATION  = 24000;
    private const HALF_DAY      = 12000;

    public function __construct(
        private readonly RedstoneEngine $engine,
        private readonly RedstoneConfig $cfg
    ) {}

    public function onRun(): void {
        $wm = $this->engine->getPlugin()->getServer()->getWorldManager();

        foreach ($wm->getWorlds() as $world) {
            if ($this->cfg->isWorldDisabled($world->getFolderName())) {
                continue;
            }
            $this->updateSensorsInWorld($world);
        }
    }

    private function computeSkySignal(World $world): int {
        $time     = $world->getTime() % self::DAY_DURATION;
        $distance = abs($time - self::NOON_TIME);
        $signal   = (int) max(0, round(15 - ($distance / self::HALF_DAY) * 15));
        return min(15, max(0, $signal));
    }

    private function updateSensorsInWorld(World $world): void {
        $signal = $this->computeSkySignal($world);

        foreach ($world->getLoadedChunks() as $chunkHash => $chunk) {
            World::getXZ($chunkHash, $chunkX, $chunkZ);
            $baseX = $chunkX << 4;
            $baseZ = $chunkZ << 4;

            for ($x = 0; $x < 16; $x++) {
                for ($z = 0; $z < 16; $z++) {
                    $bx = $baseX + $x;
                    $bz = $baseZ + $z;

                    for ($y = $world->getMinY(); $y <= $world->getMaxY(); $y++) {
                        $block = $world->getBlockAt($bx, $y, $bz);

                        if (!($block instanceof DaylightSensor)) {
                            continue;
                        }

                        if (!($block instanceof AnalogRedstoneSignalEmitter)) {
                            continue;
                        }

                        $current = $block->getSignalStrength();

                        if ($current === $signal) {
                            continue;
                        }

                        $block->setSignalStrength($signal);
                        $world->setBlock($block->getPosition(), $block);
                        $this->engine->notifyChange($block->getPosition());
                    }
                }
            }
        }
    }
}
