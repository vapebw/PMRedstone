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

namespace vape\pmredstone\util;

use pocketmine\block\Air;
use pocketmine\block\Bedrock;
use pocketmine\block\Block;
use pocketmine\block\Obsidian;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\math\Facing;

final class BlockUtil {

    /** @var array<int, true>  blockTypeId => true for piston-like blocks */
    private static array $pistonIds = [];

    /** @var array<int, true>  blockTypeId => true for sticky piston-like blocks */
    private static array $stickyPistonIds = [];

    /** @var array<int, true>  blockTypeId => true for blocks that cannot be pushed */
    private static array $immovableIds = [];

    public static function registerPistonId(int $typeId, bool $sticky = false): void {
        self::$pistonIds[$typeId] = true;
        if ($sticky) {
            self::$stickyPistonIds[$typeId] = true;
        }
    }

    public static function registerImmovableId(int $typeId): void {
        self::$immovableIds[$typeId] = true;
    }

    public static function isPiston(Block $block): bool {
        return isset(self::$pistonIds[$block->getTypeId()]);
    }

    public static function isStickyPiston(Block $block): bool {
        return isset(self::$stickyPistonIds[$block->getTypeId()]);
    }

    public static function getPistonFacing(Block $block): int {
        if ($block instanceof HorizontalFacing) {
            return $block->getFacing();
        }
        return Facing::NORTH;
    }

    public static function isMovable(Block $block): bool {
        if ($block instanceof Air) {
            return true;
        }

        if ($block instanceof Bedrock || $block instanceof Obsidian) {
            return false;
        }

        if (isset(self::$immovableIds[$block->getTypeId()])) {
            return false;
        }

        if ($block->getBreakInfo()->getHardness() < 0) {
            return false;
        }

        return true;
    }

    public static function posKey(int $x, int $y, int $z): string {
        return "$x:$y:$z";
    }
}
