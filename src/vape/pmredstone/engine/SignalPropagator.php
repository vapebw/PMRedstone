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
use pocketmine\block\Button;
use pocketmine\block\DaylightSensor;
use pocketmine\block\Lever;
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\block\SimplePressurePlate;
use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class SignalPropagator {

    public static function calculatePowerAt(RedstoneEngine $engine, World $world, Block $block, Vector3 $pos): int {
        $sourceStrength = self::getSourceStrength($block);
        if ($sourceStrength >= 0) {
            return $sourceStrength;
        }

        $max = 0;

        foreach (Facing::ALL as $face) {
            [$dx, $dy, $dz] = Facing::OFFSET[$face];
            $nx = (int) $pos->x + $dx;
            $ny = (int) $pos->y + $dy;
            $nz = (int) $pos->z + $dz;

            if (!$world->isChunkLoaded($nx >> 4, $nz >> 4)) {
                continue;
            }

            $neighbor = $world->getBlockAt($nx, $ny, $nz);
            $oppFace  = Facing::opposite($face);
            $power    = self::getOutputToward($engine, $world, $neighbor, $oppFace, new Vector3($nx, $ny, $nz));

            if ($power > $max) {
                $max = $power;
            }
        }

        return $max;
    }

    public static function getSourceStrength(Block $block): int {
        if ($block instanceof Lever) {
            return $block->isActivated() ? 15 : 0;
        }

        if ($block instanceof Button) {
            return $block->isPressed() ? 15 : 0;
        }

        if ($block instanceof SimplePressurePlate) {
            return $block->isPressed() ? 15 : 0;
        }

        if ($block instanceof RedstoneTorch) {
            return 15;
        }

        if ($block instanceof DaylightSensor && $block instanceof AnalogRedstoneSignalEmitter) {
            return $block->getSignalStrength();
        }

        return -1;
    }

    private static function getOutputToward(
        RedstoneEngine $engine,
        World $world,
        Block $neighbor,
        int $towardFace,
        Vector3 $neighborPos
    ): int {
        if ($neighbor instanceof Lever) {
            return $neighbor->isActivated() ? 15 : 0;
        }

        if ($neighbor instanceof Button) {
            return $neighbor->isPressed() ? 15 : 0;
        }

        if ($neighbor instanceof SimplePressurePlate) {
            return $neighbor->isPressed() ? 15 : 0;
        }

        if ($neighbor instanceof RedstoneTorch) {
            return $towardFace !== Facing::DOWN ? 15 : 0;
        }

        if ($neighbor instanceof RedstoneWire && $neighbor instanceof AnalogRedstoneSignalEmitter) {
            $stored = $engine->getStoredPower($world, (int) $neighborPos->x, (int) $neighborPos->y, (int) $neighborPos->z);
            return max(0, $stored - 1);
        }

        if ($neighbor instanceof RedstoneRepeater
            && $neighbor instanceof PoweredByRedstone
            && $neighbor instanceof HorizontalFacing
        ) {
            if (!$neighbor->isPowered()) {
                return 0;
            }
            return $towardFace === $neighbor->getFacing() ? 15 : 0;
        }

        if ($neighbor instanceof RedstoneComparator
            && $neighbor instanceof PoweredByRedstone
            && $neighbor instanceof HorizontalFacing
            && $neighbor instanceof AnalogRedstoneSignalEmitter
        ) {
            if (!$neighbor->isPowered()) {
                return 0;
            }
            return $towardFace === $neighbor->getFacing() ? $neighbor->getSignalStrength() : 0;
        }

        if ($neighbor instanceof DaylightSensor && $neighbor instanceof AnalogRedstoneSignalEmitter) {
            return $neighbor->getSignalStrength();
        }

        return 0;
    }

    public static function isSource(Block $block): bool {
        return $block instanceof Lever
            || $block instanceof Button
            || $block instanceof SimplePressurePlate
            || $block instanceof RedstoneTorch
            || $block instanceof DaylightSensor;
    }
}
