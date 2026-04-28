<?php

declare(strict_types=1);

namespace vape\pmredstone\block;

use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockToolType;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockTypeNames as Ids;
use pocketmine\data\bedrock\block\convert\Model;
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

        $breakInfo = new BlockTypeInfo(new BlockBreakInfo(1.5, BlockToolType::PICKAXE, 0, 7.5));
        $indestructible = new BlockTypeInfo(BlockBreakInfo::indestructible());

        self::$piston = new PistonBlock(new BlockIdentifier(PistonBlockIds::piston()), "Piston", $breakInfo);
        self::$stickyPiston = new StickyPistonBlock(new BlockIdentifier(PistonBlockIds::stickyPiston()), "Sticky Piston", $breakInfo);
        self::$pistonHead = new PistonHeadBlock(new BlockIdentifier(PistonBlockIds::pistonHead()), "Piston Head", $breakInfo);
        self::$stickyPistonHead = new StickyPistonHeadBlock(new BlockIdentifier(PistonBlockIds::stickyPistonHead()), "Sticky Piston Head", $breakInfo);
        self::$movingBlock = new MovingBlock(new BlockIdentifier(PistonBlockIds::movingBlock()), "Moving Block", $indestructible);

        $runtime = RuntimeBlockStateRegistry::getInstance();
        $runtime->register(self::$piston);
        $runtime->register(self::$stickyPiston);
        $runtime->register(self::$pistonHead);
        $runtime->register(self::$stickyPistonHead);
        $runtime->register(self::$movingBlock);

        $registrar = GlobalBlockStateHandlers::getRegistrar();
        $common = CommonProperties::getInstance();
        $registrar->mapModel(Model::create(self::$piston, Ids::PISTON)->properties([
            new \pocketmine\data\bedrock\block\convert\property\ValueFromIntProperty(
                \pocketmine\data\bedrock\block\BlockStateNames::FACING_DIRECTION,
                \pocketmine\data\bedrock\block\convert\property\IntFromRawStateMap::int([
                    \pocketmine\math\Facing::DOWN => 0,
                    \pocketmine\math\Facing::UP => 1,
                    \pocketmine\math\Facing::NORTH => 3,
                    \pocketmine\math\Facing::SOUTH => 2,
                    \pocketmine\math\Facing::WEST => 5,
                    \pocketmine\math\Facing::EAST => 4
                ]),
                fn(PistonBlock $b) => $b->getFacing(),
                fn(PistonBlock $b, int $v) => $b->setFacing($v)
            ),
            new \pocketmine\data\bedrock\block\convert\property\BoolProperty("piston_bit", fn(PistonBlock $b) => $b->isExtended(), fn(PistonBlock $b, bool $v) => $b->setExtended($v))
        ]));
        $registrar->mapModel(Model::create(self::$stickyPiston, Ids::STICKY_PISTON)->properties([
            new \pocketmine\data\bedrock\block\convert\property\ValueFromIntProperty(
                \pocketmine\data\bedrock\block\BlockStateNames::FACING_DIRECTION,
                \pocketmine\data\bedrock\block\convert\property\IntFromRawStateMap::int([
                    \pocketmine\math\Facing::DOWN => 0,
                    \pocketmine\math\Facing::UP => 1,
                    \pocketmine\math\Facing::NORTH => 3,
                    \pocketmine\math\Facing::SOUTH => 2,
                    \pocketmine\math\Facing::WEST => 5,
                    \pocketmine\math\Facing::EAST => 4
                ]),
                fn(StickyPistonBlock $b) => $b->getFacing(),
                fn(StickyPistonBlock $b, int $v) => $b->setFacing($v)
            ),
            new \pocketmine\data\bedrock\block\convert\property\BoolProperty("piston_bit", fn(StickyPistonBlock $b) => $b->isExtended(), fn(StickyPistonBlock $b, bool $v) => $b->setExtended($v))
        ]));
        $registrar->mapModel(Model::create(self::$pistonHead, Ids::PISTON_ARM_COLLISION)->properties([
            new \pocketmine\data\bedrock\block\convert\property\ValueFromIntProperty(
                \pocketmine\data\bedrock\block\BlockStateNames::FACING_DIRECTION,
                \pocketmine\data\bedrock\block\convert\property\IntFromRawStateMap::int([
                    \pocketmine\math\Facing::DOWN => 0,
                    \pocketmine\math\Facing::UP => 1,
                    \pocketmine\math\Facing::NORTH => 3,
                    \pocketmine\math\Facing::SOUTH => 2,
                    \pocketmine\math\Facing::WEST => 5,
                    \pocketmine\math\Facing::EAST => 4
                ]),
                fn(PistonHeadBlock $b) => $b->getFacing(),
                fn(PistonHeadBlock $b, int $v) => $b->setFacing($v)
            ),
            new \pocketmine\data\bedrock\block\convert\property\BoolProperty("piston_type_bit", fn(PistonHeadBlock $b) => $b->isSticky(), fn($b, $v) => null)
        ]));
        $registrar->mapModel(Model::create(self::$stickyPistonHead, Ids::STICKY_PISTON_ARM_COLLISION)->properties([
            new \pocketmine\data\bedrock\block\convert\property\ValueFromIntProperty(
                \pocketmine\data\bedrock\block\BlockStateNames::FACING_DIRECTION,
                \pocketmine\data\bedrock\block\convert\property\IntFromRawStateMap::int([
                    \pocketmine\math\Facing::DOWN => 0,
                    \pocketmine\math\Facing::UP => 1,
                    \pocketmine\math\Facing::NORTH => 3,
                    \pocketmine\math\Facing::SOUTH => 2,
                    \pocketmine\math\Facing::WEST => 5,
                    \pocketmine\math\Facing::EAST => 4
                ]),
                fn(StickyPistonHeadBlock $b) => $b->getFacing(),
                fn(StickyPistonHeadBlock $b, int $v) => $b->setFacing($v)
            ),
            new \pocketmine\data\bedrock\block\convert\property\BoolProperty("piston_type_bit", fn(StickyPistonHeadBlock $b) => $b->isSticky(), fn($b, $v) => null)
        ]));
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
