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
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\HorizontalFacing;
use pocketmine\math\Facing;
use function str_contains;
use function strtolower;
use vape\pmredstone\block\MovingBlock;
use vape\pmredstone\block\PistonBlock;
use vape\pmredstone\block\PistonHeadBlock;
use vape\pmredstone\block\StickyPistonBlock;
use vape\pmredstone\block\StickyPistonHeadBlock;

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
        if ($block instanceof PistonBlock || $block instanceof StickyPistonBlock) {
            return true;
        }

        $typeId = $block->getTypeId();
        if (isset(self::$pistonIds[$typeId])) {
            return true;
        }

        $name = strtolower($block->getName());
        if (str_contains($name, "piston")) {
            $sticky = str_contains($name, "sticky");
            self::registerPistonId($typeId, $sticky);
            return true;
        }

        return false;
    }

    public static function isStickyPiston(Block $block): bool {
        if ($block instanceof StickyPistonBlock) {
            return true;
        }

        $typeId = $block->getTypeId();
        if (isset(self::$stickyPistonIds[$typeId])) {
            return true;
        }

        if (!self::isPiston($block)) {
            return false;
        }

        return isset(self::$stickyPistonIds[$typeId]);
    }

    public static function getPistonFacing(Block $block): int {
        if ($block instanceof AnyFacing) {
            return $block->getFacing();
        }

        if ($block instanceof HorizontalFacing) {
            return $block->getFacing();
        }
        return Facing::NORTH;
    }

    public static function isPistonHead(Block $block): bool {
        return $block instanceof PistonHeadBlock || $block instanceof StickyPistonHeadBlock;
    }

    public static function isMovable(Block $block): bool {
        if ($block instanceof Air) {
            return true;
        }

        if ($block instanceof MovingBlock || self::isPistonHead($block)) {
            return false;
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

        $world = $block->getPosition()->getWorld();
        if ($world !== null && $world->getTile($block->getPosition()) !== null) {
            return false;
        }

        if ($block->getTypeId() === VanillaBlocks::AIR()->getTypeId()) {
            return true;
        }

        return true;
    }

    public static function posKey(int $x, int $y, int $z): string {
        return "$x:$y:$z";
    }
}
