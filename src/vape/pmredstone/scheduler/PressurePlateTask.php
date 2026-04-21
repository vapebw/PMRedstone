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

use pocketmine\block\SimplePressurePlate;
use pocketmine\math\AxisAlignedBB;
use pocketmine\scheduler\Task;
use pocketmine\world\World;
use vape\pmredstone\config\RedstoneConfig;
use vape\pmredstone\engine\RedstoneEngine;

final class PressurePlateTask extends Task {

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

            $plates = $registry->getPlatesForWorld($world->getId());

            if (count($plates) === 0) {
                continue;
            }

            $this->checkPlates($world, $plates);
        }
    }

    /** @param array<string, array{int, int, int}> $plates */
    private function checkPlates(World $world, array $plates): void {
        foreach ($plates as [$x, $y, $z]) {
            if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                continue;
            }

            $block = $world->getBlockAt($x, $y, $z);

            if (!($block instanceof SimplePressurePlate)) {
                continue;
            }

            $aabb = new AxisAlignedBB(
                $x,       $y,       $z,
                $x + 1.0, $y + 0.5, $z + 1.0
            );

            $shouldBePressed = count($world->getNearbyEntities($aabb)) > 0;
            $isPressed       = $block->isPressed();

            if ($shouldBePressed === $isPressed) {
                continue;
            }

            $block->setPressed($shouldBePressed);
            $world->setBlock($block->getPosition(), $block);
            $this->engine->notifyChange($block->getPosition());
        }
    }
}
