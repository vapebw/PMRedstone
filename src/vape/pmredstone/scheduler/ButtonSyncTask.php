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

use pocketmine\block\Button;
use pocketmine\scheduler\Task;
use vape\pmredstone\config\RedstoneConfig;
use vape\pmredstone\engine\RedstoneEngine;

final class ButtonSyncTask extends Task
{
    /** @var array<int, array<string, bool>> */
    private array $lastPressedState = [];

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

            $wid = $world->getId();
            $buttons = $registry->getButtonsForWorld($wid);
            if (count($buttons) === 0) {
                continue;
            }

            foreach ($buttons as $key => [$x, $y, $z]) {
                if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                    continue;
                }

                $block = $world->getBlockAt($x, $y, $z);
                if (!($block instanceof Button)) {
                    $registry->unregisterButton($block->getPosition());
                    unset($this->lastPressedState[$wid][$key]);
                    continue;
                }

                $pressed = $block->isPressed();
                $last = $this->lastPressedState[$wid][$key] ?? $pressed;

                if ($pressed !== $last) {
                    $this->engine->notifyChange($block->getPosition());
                }

                $this->lastPressedState[$wid][$key] = $pressed;
            }
        }
    }
}
