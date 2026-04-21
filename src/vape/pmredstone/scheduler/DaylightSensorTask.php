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

    private const NOON_TIME    = 6000;
    private const DAY_DURATION = 24000;
    private const HALF_DAY     = 12000;

    public function __construct(
        private readonly RedstoneEngine $engine,
        private readonly RedstoneConfig $cfg
    ) {}

    public function onRun(): void {
        $wm       = $this->engine->getPlugin()->getServer()->getWorldManager();
        $registry = $this->engine->getRegistry();

        foreach ($wm->getWorlds() as $world) {
            if ($this->cfg->isWorldDisabled($world->getFolderName())) {
                continue;
            }

            $sensors = $registry->getSensorsForWorld($world->getId());

            if (count($sensors) === 0) {
                continue;
            }

            $signal = $this->computeSkySignal($world);
            $this->updateSensors($world, $sensors, $signal);
        }
    }

    private function computeSkySignal(World $world): int {
        $time     = $world->getTime() % self::DAY_DURATION;
        $distance = abs($time - self::NOON_TIME);
        return min(15, max(0, (int) round(15 - ($distance / self::HALF_DAY) * 15)));
    }

    /** @param array<string, array{int, int, int}> $sensors */
    private function updateSensors(World $world, array $sensors, int $signal): void {
        foreach ($sensors as [$x, $y, $z]) {
            if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                continue;
            }

            $block = $world->getBlockAt($x, $y, $z);

            if (!($block instanceof DaylightSensor) || !($block instanceof AnalogRedstoneSignalEmitter)) {
                continue;
            }

            if ($block->getSignalStrength() === $signal) {
                continue;
            }

            $block->setSignalStrength($signal);
            $world->setBlock($block->getPosition(), $block);
            $this->engine->notifyChange($block->getPosition());
        }
    }
}
