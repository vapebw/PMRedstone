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

use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockToolType;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockTypeNames as Ids;
use pocketmine\data\bedrock\block\convert\Model;
use pocketmine\data\bedrock\block\convert\property\BoolProperty;
use pocketmine\data\bedrock\block\convert\property\CommonProperties;
use pocketmine\item\StringToItemParser;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use vape\pmredstone\util\BlockUtil;

final class PistonBlockRegistry {

    private static bool $bootstrapped = false;
    private static ?PistonBlock $piston = null;
    private static ?StickyPistonBlock $stickyPiston = null;
    private static ?PistonHeadBlock $pistonHead = null;
    private static ?StickyPistonHeadBlock $stickyPistonHead = null;
    private static ?MovingBlock $movingBlock = null;

    public static function bootstrap(): void {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        $pistonInfo = new BlockTypeInfo(new BlockBreakInfo(1.5, BlockToolType::PICKAXE, 0, 7.5));
        $pistonId = new BlockIdentifier(PistonBlockIds::piston(), \vape\pmredstone\tile\PistonArmTile::class);
        $stickyPistonId = new BlockIdentifier(PistonBlockIds::stickyPiston(), \vape\pmredstone\tile\PistonArmTile::class);
        
        self::$piston = new PistonBlock($pistonId, "Piston", $pistonInfo);
        self::$stickyPiston = new StickyPistonBlock($stickyPistonId, "Sticky Piston", $pistonInfo);
        self::$pistonHead = new PistonHeadBlock(new BlockIdentifier(PistonBlockIds::pistonHead(), \vape\pmredstone\tile\PistonArmTile::class), "Piston Head", $pistonInfo);
        self::$stickyPistonHead = new StickyPistonHeadBlock(new BlockIdentifier(PistonBlockIds::stickyPistonHead(), \vape\pmredstone\tile\PistonArmTile::class), "Sticky Piston Head", $pistonInfo);
        $indestructible = new BlockTypeInfo(BlockBreakInfo::indestructible());
        self::$movingBlock = new MovingBlock(new BlockIdentifier(PistonBlockIds::movingBlock()), "Moving Block", $indestructible);

        $runtime = RuntimeBlockStateRegistry::getInstance();
        $runtime->register(self::$piston);
        $runtime->register(self::$stickyPiston);
        $runtime->register(self::$pistonHead);
        $runtime->register(self::$stickyPistonHead);
        $runtime->register(self::$movingBlock);

        $registrar = GlobalBlockStateHandlers::getRegistrar();
        $common = CommonProperties::getInstance();
        $registrar->mapModel(Model::create(self::$piston, Ids::PISTON)
            ->properties([
                $common->anyFacingClassic
            ]));
        $registrar->mapModel(Model::create(self::$stickyPiston, Ids::STICKY_PISTON)
            ->properties([
                $common->anyFacingClassic
            ]));
        $registrar->mapModel(Model::create(self::$pistonHead, Ids::PISTON_ARM_COLLISION)->properties([$common->anyFacingClassic]));
        $registrar->mapModel(Model::create(self::$stickyPistonHead, Ids::STICKY_PISTON_ARM_COLLISION)->properties([$common->anyFacingClassic]));
        $registrar->mapModel(Model::create(self::$movingBlock, Ids::MOVING_BLOCK));

        $parser = StringToItemParser::getInstance();
        $parser->registerBlock("piston", fn(string $_input) => clone self::$piston);
        $parser->registerBlock("sticky_piston", fn(string $_input) => clone self::$stickyPiston);
        $parser->registerBlock("piston_head", fn(string $_input) => clone self::$pistonHead);
        $parser->registerBlock("sticky_piston_head", fn(string $_input) => clone self::$stickyPistonHead);

        BlockUtil::registerPistonId(self::$piston->getTypeId(), false);
        BlockUtil::registerPistonId(self::$stickyPiston->getTypeId(), true);
        BlockUtil::registerImmovableId(self::$pistonHead->getTypeId());
        BlockUtil::registerImmovableId(self::$stickyPistonHead->getTypeId());
        BlockUtil::registerImmovableId(self::$movingBlock->getTypeId());
    }

    public static function piston(): PistonBlock {
        self::bootstrap();
        return clone self::$piston;
    }

    public static function stickyPiston(): StickyPistonBlock {
        self::bootstrap();
        return clone self::$stickyPiston;
    }

    public static function pistonHead(): PistonHeadBlock {
        self::bootstrap();
        return clone self::$pistonHead;
    }

    public static function stickyPistonHead(): StickyPistonHeadBlock {
        self::bootstrap();
        return clone self::$stickyPistonHead;
    }

    public static function movingBlock(): MovingBlock {
        self::bootstrap();
        return clone self::$movingBlock;
    }
}
