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
use pocketmine\block\Opaque;
use pocketmine\block\Redstone;
use pocketmine\block\RedstoneComparator;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneTorch;
use pocketmine\block\RedstoneWire;
use pocketmine\block\SimplePressurePlate;
use pocketmine\block\utils\AnalogRedstoneSignalEmitter;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\math\Facing;
use pocketmine\world\World;

final class SignalPropagator
{
    private const COMPARATOR_DELAY_TICKS = 2;

    public static function calculatePowerAt(
        RedstoneEngine $engine,
        World $world,
        Block $block,
        int $x,
        int $y,
        int $z
    ): int {
        if ($block instanceof RedstoneRepeater && $block instanceof PoweredByRedstone) {
            return $block->isPowered() ? 15 : 0;
        }

        if ($block instanceof RedstoneComparator && $block instanceof AnalogRedstoneSignalEmitter) {
            return $block->getOutputSignalStrength();
        }

        if ($block instanceof RedstoneWire) {
            return self::calculateWirePowerAt($engine, $world, $x, $y, $z);
        }

        $sourceStrength = self::getSourceStrength($engine, $world, $block, $x, $y, $z);
        if ($sourceStrength >= 0) {
            return $sourceStrength;
        }

        $max = 0;
        $facesToCheck = Facing::ALL;

        if ($block instanceof RedstoneRepeater || $block instanceof RedstoneComparator) {
            $facesToCheck = [Facing::opposite($block->getFacing())];
        }

        foreach ($facesToCheck as $face) {
            [$dx, $dy, $dz] = Facing::OFFSET[$face];
            $nx = $x + $dx;
            $ny = $y + $dy;
            $nz = $z + $dz;

            if (!$world->isChunkLoaded($nx >> 4, $nz >> 4)) {
                continue;
            }

            $neighbor = $world->getBlockAt($nx, $ny, $nz);
            $power = self::getOutputToward($engine, $world, $neighbor, Facing::opposite($face), $nx, $ny, $nz, $block);

            if ($power > $max) {
                $max = $power;
            }
        }

        return $max;
    }

    public static function calculateRepeaterInput(
        RedstoneEngine $engine,
        World $world,
        RedstoneRepeater $block,
        int $x,
        int $y,
        int $z
    ): int {
        return self::getSignalFromFace($engine, $world, $x, $y, $z, Facing::opposite($block->getFacing()), $block);
    }

    public static function calculateComparatorOutput(
        RedstoneEngine $engine,
        World $world,
        RedstoneComparator $block,
        int $x,
        int $y,
        int $z
    ): int {
        $back = Facing::opposite($block->getFacing());
        $left = Facing::rotateY($block->getFacing(), false);
        $right = Facing::rotateY($block->getFacing(), true);

        $mainInput = self::getSignalFromFace($engine, $world, $x, $y, $z, $back, $block);
        $sideInput = max(
            self::getSignalFromFace($engine, $world, $x, $y, $z, $left, $block),
            self::getSignalFromFace($engine, $world, $x, $y, $z, $right, $block)
        );

        if ($block->isSubtractMode()) {
            return max(0, $mainInput - $sideInput);
        }

        return $mainInput >= $sideInput ? $mainInput : 0;
    }

    public static function getComparatorDelayTicks() : int
    {
        return self::COMPARATOR_DELAY_TICKS;
    }

    private static function calculateWirePowerAt(
        RedstoneEngine $engine,
        World $world,
        int $x,
        int $y,
        int $z
    ): int {
        $max = 0;
        $wire = $world->getBlockAt($x, $y, $z);

        foreach (Facing::ALL as $face) {
            [$dx, $dy, $dz] = Facing::OFFSET[$face];
            $nx = $x + $dx;
            $ny = $y + $dy;
            $nz = $z + $dz;

            if (!$world->isChunkLoaded($nx >> 4, $nz >> 4)) {
                continue;
            }

            $neighbor = $world->getBlockAt($nx, $ny, $nz);
            $power = self::getOutputToward($engine, $world, $neighbor, Facing::opposite($face), $nx, $ny, $nz, $wire);

            if ($power > $max) {
                $max = $power;
            }
        }

        return $max;
    }

    public static function getSourceStrength(RedstoneEngine $engine, World $world, Block $block, int $x, int $y, int $z): int
    {
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
            $attachedPos = null;
            if ($block instanceof HorizontalFacing) {
                $facing = $block->getFacing();
                $attachedPos = $block->getPosition()->getSide(Facing::opposite($facing));
            } else {
                $attachedPos = $block->getPosition()->getSide(Facing::DOWN);
            }

            if ($engine->getStoredPower($world, $attachedPos->getFloorX(), $attachedPos->getFloorY(), $attachedPos->getFloorZ()) > 0) {
                return 0;
            }
            return 15;
        }

        if ($block instanceof Redstone) {
            return 15;
        }

        if ($block instanceof DaylightSensor && $block instanceof AnalogRedstoneSignalEmitter) {
            return $block->getOutputSignalStrength();
        }

        return -1;
    }

    private static function getOutputToward(
        RedstoneEngine $engine,
        World $world,
        Block $neighbor,
        int $towardFace,
        int $nx,
        int $ny,
        int $nz,
        Block $requestingBlock
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
            $stored = $engine->getStoredPower($world, $nx, $ny, $nz);
            return $requestingBlock instanceof RedstoneWire ? max(0, $stored - 1) : $stored;
        }

        if ($neighbor instanceof Redstone) {
            return 15;
        }

        if ($neighbor instanceof Opaque) {
            if ($requestingBlock instanceof Opaque) {
                return 0;
            }
            $stored = $engine->getStoredPower($world, $nx, $ny, $nz);
            return max(0, $stored - 1);
        }

        if (
            $neighbor instanceof RedstoneRepeater
            && $neighbor instanceof PoweredByRedstone
            && $neighbor instanceof HorizontalFacing
        ) {
            if (!$neighbor->isPowered()) {
                return 0;
            }
            return $towardFace === $neighbor->getFacing() ? 15 : 0;
        }

        if (
            $neighbor instanceof RedstoneComparator
            && $neighbor instanceof PoweredByRedstone
            && $neighbor instanceof HorizontalFacing
            && $neighbor instanceof AnalogRedstoneSignalEmitter
        ) {
            if (!$neighbor->isPowered()) {
                return 0;
            }
            return $towardFace === $neighbor->getFacing() ? $neighbor->getOutputSignalStrength() : 0;
        }

        if ($neighbor instanceof DaylightSensor && $neighbor instanceof AnalogRedstoneSignalEmitter) {
            return $neighbor->getOutputSignalStrength();
        }

        return 0;
    }

    private static function getSignalFromFace(
        RedstoneEngine $engine,
        World $world,
        int $x,
        int $y,
        int $z,
        int $face,
        Block $requestingBlock
    ) : int {
        [$dx, $dy, $dz] = Facing::OFFSET[$face];
        $nx = $x + $dx;
        $ny = $y + $dy;
        $nz = $z + $dz;

        if (!$world->isChunkLoaded($nx >> 4, $nz >> 4)) {
            return 0;
        }

        $neighbor = $world->getBlockAt($nx, $ny, $nz);
        return self::getOutputToward($engine, $world, $neighbor, Facing::opposite($face), $nx, $ny, $nz, $requestingBlock);
    }

    public static function isSource(Block $block): bool
    {
        return $block instanceof Lever
            || $block instanceof Button
            || $block instanceof SimplePressurePlate
            || $block instanceof RedstoneTorch
            || $block instanceof Redstone
            || $block instanceof DaylightSensor;
    }
}
