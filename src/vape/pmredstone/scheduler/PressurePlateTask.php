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
use pocketmine\math\Vector3;
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
        $wm = $this->engine->getPlugin()->getServer()->getWorldManager();

        foreach ($wm->getWorlds() as $world) {
            if ($this->cfg->isWorldDisabled($world->getFolderName())) {
                continue;
            }
            $this->checkPlatesInWorld($world);
        }
    }

    private function checkPlatesInWorld(World $world): void {
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

                        if (!($block instanceof SimplePressurePlate)) {
                            continue;
                        }

                        $aabb = new AxisAlignedBB(
                            $bx,        $y,        $bz,
                            $bx + 1.0,  $y + 0.5,  $bz + 1.0
                        );

                        $entitiesAbove   = $world->getNearbyEntities($aabb);
                        $shouldBePressed = count($entitiesAbove) > 0;
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
        }
    }
}
