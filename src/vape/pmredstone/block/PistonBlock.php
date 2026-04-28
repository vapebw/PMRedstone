<?php

declare(strict_types=1);

namespace vape\pmredstone\block;

use pocketmine\block\Block;
use pocketmine\block\Opaque;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use vape\pmredstone\Loader;
use vape\pmredstone\block\PistonHeadBlock;

class PistonBlock extends Opaque implements AnyFacing
{
    use AnyFacingTrait;

    protected bool $extended = false;

    protected function describeBlockOnlyState(RuntimeDataDescriber $w): void
    {
        $w->facing($this->facing);
        $w->bool($this->extended);
    }

    public function isSticky(): bool
    {
        return false;
    }

    public function isExtended(): bool
    {
        return $this->extended;
    }

    public function setExtended(bool $extended): self
    {
        $this->extended = $extended;
        return $this;
    }

    public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool
    {
        $world = $this->position->getWorld();
        $frontPos = $this->position->getSide($this->facing);
        $front = $world->getBlock($frontPos);

        if ($front instanceof PistonHeadBlock && $front->getFacing() === $this->facing) {
            $world->setBlock($frontPos, \pocketmine\block\VanillaBlocks::AIR());
        }

        return parent::onBreak($item, $player, $returnedItems);
    }
    private function resolvePlacementFacing(Block $blockReplace, ?Player $player, int $face): int
    {
        if ($player === null) {
            return $face;
        }

        return Facing::fromDirection($player->getDirectionVector());
    }

    public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null): bool
    {
        $this->facing = $this->resolvePlacementFacing($blockReplace, $player, $face);
        $this->extended = false;

        $result = parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);

        $plugin = Loader::getInstance();
        if ($plugin->getRedstoneConfig()->isDebugPiston()) {
            $plugin->getLogger()->debug(sprintf(
                "[Piston] Place @ %d,%d,%d face=%s playerFacing=%s storedFacing=%s extended=false",
                $blockReplace->getPosition()->getFloorX(),
                $blockReplace->getPosition()->getFloorY(),
                $blockReplace->getPosition()->getFloorZ(),
                self::facingName($face),
                $player !== null ? self::facingName($player->getHorizontalFacing()) : "none",
                self::facingName($this->facing)
            ));
        }

        return $result;
    }

    public static function facingName(int $facing): string
    {
        return match ($facing) {
            Facing::DOWN => "down",
            Facing::UP => "up",
            Facing::NORTH => "north",
            Facing::SOUTH => "south",
            Facing::WEST => "west",
            Facing::EAST => "east",
            default => "unknown($facing)",
        };
    }
}
