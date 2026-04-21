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

namespace vape\pmredstone\listener;

use pocketmine\block\Button;
use pocketmine\block\DaylightSensor;
use pocketmine\block\Lever;
use pocketmine\block\Opaque;
use pocketmine\block\Redstone;
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneLamp;
use pocketmine\block\RedstoneOre;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\block\SimplePressurePlate;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\event\Listener;
use pocketmine\math\Facing;
use pocketmine\scheduler\Task;
use pocketmine\world\Position;
use vape\pmredstone\config\RedstoneConfig;
use vape\pmredstone\engine\RedstoneEngine;
use vape\pmredstone\engine\SignalPropagator;

final class RedstoneListener implements Listener
{

    public function __construct(
        private readonly RedstoneEngine $engine,
        private readonly RedstoneConfig $cfg
    ) {
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        if (!$this->cfg->isEnabled()) {
            return;
        }

        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();

        if ($this->cfg->isWorldDisabled($world->getFolderName())) {
            return;
        }

        if (
            $block instanceof Lever
            || $block instanceof Button
            || $block instanceof DaylightSensor
            || $block instanceof RedstoneRepeater
            || $block instanceof RedstoneComparator
        ) {
            $this->engine->notifyChange($block->getPosition());
            $pos = $block->getPosition();
            $this->engine->getPlugin()->getScheduler()->scheduleDelayedTask(
                new class ($this->engine, $pos) extends Task {
                public function __construct(private readonly RedstoneEngine $engine, private readonly Position $pos)
                {}

                public function onRun(): void
                {
                    $this->engine->notifyChange($this->pos);
                }
                },
                1
            );
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        if (!$this->cfg->isEnabled()) {
            return;
        }

        $world = $event->getPlayer()->getWorld();
        if ($this->cfg->isWorldDisabled($world->getFolderName())) {
            return;
        }

        $positions = [];
        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            $pos = new Position($x, $y, $z, $world);
            $positions[] = $pos;

            if ($block instanceof DaylightSensor) {
                $this->engine->getRegistry()->registerSensor($pos);
            }

            if ($block instanceof SimplePressurePlate) {
                $this->engine->getRegistry()->registerPlate($pos);
            }

            if ($block instanceof Button) {
                $this->engine->getRegistry()->registerButton($pos);
            }
        }

        $this->engine->getPlugin()->getScheduler()->scheduleDelayedTask(
            new class ($this->engine, $positions) extends \pocketmine\scheduler\Task {
            public function __construct(private RedstoneEngine $engine, private array $positions)
            {}
            public function onRun(): void
            {
                foreach ($this->positions as $pos) {
                    $this->engine->notifyChange($pos);
                    foreach (Facing::ALL as $face) {
                        $this->engine->notifyChange($pos->getSide($face));
                    }
                }
            }
            },
            1
        );
    }

    public function onBlockBreak(BlockBreakEvent $event): void
    {
        if (!$this->cfg->isEnabled()) {
            return;
        }

        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();
        if ($this->cfg->isWorldDisabled($world->getFolderName())) {
            return;
        }

        $pos = $block->getPosition();

        if ($block instanceof DaylightSensor) {
            $this->engine->getRegistry()->unregisterSensor($pos);
        }

        if ($block instanceof SimplePressurePlate) {
            $this->engine->getRegistry()->unregisterPlate($pos);
        }

        if ($block instanceof Button) {
            $this->engine->getRegistry()->unregisterButton($pos);
        }

        $this->engine->getPlugin()->getScheduler()->scheduleDelayedTask(
            new class ($this->engine, $pos) extends \pocketmine\scheduler\Task {
            public function __construct(private RedstoneEngine $engine, private Position $pos)
            {}
            public function onRun(): void
            {
                $this->engine->notifyChange($this->pos);
                foreach (Facing::ALL as $face) {
                    $this->engine->notifyChange($this->pos->getSide($face));
                }
            }
            },
            1
        );
    }

    public function onWorldUnload(WorldUnloadEvent $event): void
    {
        $this->engine->invalidateWorld($event->getWorld()->getId());
    }

    private function isRedstoneRelated(object $block): bool
    {
        return $block instanceof Lever
            || $block instanceof Button
            || $block instanceof RedstoneTorch
            || $block instanceof RedstoneWire
            || $block instanceof RedstoneLamp
            || $block instanceof RedstoneOre
            || $block instanceof RedstoneRepeater
            || $block instanceof RedstoneComparator
            || $block instanceof DaylightSensor
            || $block instanceof SimplePressurePlate
            || $block instanceof Redstone
            || $block instanceof Opaque
            || SignalPropagator::isSource($block);
    }
}