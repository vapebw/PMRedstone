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
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneLamp;
use pocketmine\block\RedstoneOre;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\block\SimplePressurePlate;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\event\Listener;
use pocketmine\world\Position;
use vape\pmredstone\config\RedstoneConfig;
use vape\pmredstone\engine\RedstoneEngine;
use vape\pmredstone\engine\SignalPropagator;

final class RedstoneListener implements Listener {

    public function __construct(
        private readonly RedstoneEngine $engine,
        private readonly RedstoneConfig $cfg
    ) {}

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        if (!$this->cfg->isEnabled()) {
            return;
        }

        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();

        if ($this->cfg->isWorldDisabled($world->getFolderName())) {
            return;
        }

        if ($block instanceof Lever
            || $block instanceof Button
            || $block instanceof DaylightSensor
            || $block instanceof RedstoneRepeater
            || $block instanceof RedstoneComparator
        ) {
            $this->engine->notifyChange($block->getPosition());
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        if (!$this->cfg->isEnabled()) {
            return;
        }

        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            $world = $event->getPlayer()->getWorld();

            if ($this->cfg->isWorldDisabled($world->getFolderName())) {
                return;
            }

            if ($this->isRedstoneRelated($block)) {
                $pos = new Position($x, $y, $z, $world);
                $this->engine->notifyChange($pos);

                if ($this->cfg->isDebugEnabled()) {
                    $this->engine->getPlugin()->getLogger()->debug(
                        "[Place] Redstone block placed: " . $block->getName() . " @ $x,$y,$z"
                    );
                }
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        if (!$this->cfg->isEnabled()) {
            return;
        }

        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();

        if ($this->cfg->isWorldDisabled($world->getFolderName())) {
            return;
        }

        if ($this->isRedstoneRelated($block)) {
            $this->engine->notifyChange($block->getPosition());
        }
    }

    public function onWorldUnload(WorldUnloadEvent $event): void {
        $this->engine->invalidateWorld($event->getWorld()->getId());
    }

    private function isRedstoneRelated(object $block): bool {
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
            || SignalPropagator::isSource($block);
    }
}
