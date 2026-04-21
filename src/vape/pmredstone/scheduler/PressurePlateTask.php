<?php

declare(strict_types=1);

namespace vape\pmredstone\scheduler;

use pocketmine\block\SimplePressurePlate;
use pocketmine\math\AxisAlignedBB;
use pocketmine\scheduler\Task;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use vape\pmredstone\config\RedstoneConfig;
use vape\pmredstone\engine\RedstoneEngine;

final class PressurePlateTask extends Task
{
    public function __construct(
        private readonly RedstoneEngine $engine,
        private readonly RedstoneConfig $cfg
    ) {
    }

    public function onRun(): void
    {
        $wm = $this->engine->getPlugin()->getServer()->getWorldManager();
        $registry = $this->engine->getRegistry();

        foreach ($wm->getWorlds() as $world) {
            if ($this->cfg->isWorldDisabled($world->getFolderName())) {
                continue;
            }

            $plates = $registry->getPlatesForWorld($world->getId());

            if (count($plates) === 0) {
                $this->discoverPlates($world);
                $plates = $registry->getPlatesForWorld($world->getId());
            }

            if (count($plates) === 0) {
                continue;
            }

            $this->checkPlates($world, $plates);
        }
    }

    private function checkPlates(World $world, array $plates): void
    {
        foreach ($plates as $key => [$x, $y, $z]) {
            if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                continue;
            }

            $block = $world->getBlockAt($x, $y, $z);

            if (!($block instanceof SimplePressurePlate)) {
                continue;
            }

            $aabb = new AxisAlignedBB(
                $x,
                $y,
                $z,
                $x + 1.0,
                $y + 0.5,
                $z + 1.0
            );

            $shouldBePressed = count($world->getNearbyEntities($aabb)) > 0;
            $isPressed = $block->isPressed();

            if ($shouldBePressed !== $isPressed) {
                $block->setPressed($shouldBePressed);
                $world->setBlock($block->getPosition(), $block);
                $this->engine->notifyChange($block->getPosition());
            }
        }
    }

    private function discoverPlates(World $world): void
    {
        $registry = $this->engine->getRegistry();

        foreach ($world->getLoadedChunks() as $chunkHash => $chunk) {
            World::getXZ($chunkHash, $chunkX, $chunkZ);

            for ($cx = 0; $cx < Chunk::EDGE_LENGTH; $cx++) {
                for ($cz = 0; $cz < Chunk::EDGE_LENGTH; $cz++) {
                    $x = ($chunkX << 4) + $cx;
                    $z = ($chunkZ << 4) + $cz;

                    for ($y = World::Y_MIN; $y <= World::Y_MAX; $y++) {
                        $block = $world->getBlockAt($x, $y, $z);
                        if ($block instanceof SimplePressurePlate) {
                            $registry->registerPlate($block->getPosition());
                        }
                    }
                }
            }
        }
    }
}
