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

use pocketmine\scheduler\Task;
use pocketmine\world\Position;
use vape\pmredstone\engine\RedstoneEngine;

final class PistonMoveTask extends Task {

    public function __construct(
        private readonly RedstoneEngine $engine,
        private readonly Position $pistonPos,
        private readonly int $facing,
        private readonly bool $sticky,
        private readonly bool $extending
    ) {}

    public function onRun(): void {
        $world = $this->pistonPos->getWorld();

        if ($world === null) {
            return;
        }

        if (!$world->isChunkLoaded(
            (int) $this->pistonPos->x >> 4,
            (int) $this->pistonPos->z >> 4
        )) {
            return;
        }

        $pistonEngine = $this->engine->getPistonEngine();

        if ($this->extending) {
            $pistonEngine->executePush($world, $this->pistonPos, $this->facing, $this->sticky);
        } else {
            $pistonEngine->executeRetract($world, $this->pistonPos, $this->facing, $this->sticky);
        }
    }
}
