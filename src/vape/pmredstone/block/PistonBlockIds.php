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

namespace vape\pmredstone\block;

use pocketmine\block\BlockTypeIds;

final class PistonBlockIds {
    private static ?int $piston = null;
    private static ?int $stickyPiston = null;
    private static ?int $pistonHead = null;
    private static ?int $stickyPistonHead = null;
    private static ?int $movingBlock = null;

    public static function piston(): int {
        return self::$piston ??= BlockTypeIds::newId();
    }

    public static function stickyPiston(): int {
        return self::$stickyPiston ??= BlockTypeIds::newId();
    }

    public static function pistonHead(): int {
        return self::$pistonHead ??= BlockTypeIds::newId();
    }

    public static function stickyPistonHead(): int {
        return self::$stickyPistonHead ??= BlockTypeIds::newId();
    }

    public static function movingBlock(): int {
        return self::$movingBlock ??= BlockTypeIds::newId();
    }
}
