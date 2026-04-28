<?php

declare(strict_types=1);

namespace vape\pmredstone\block;

use pocketmine\block\Transparent;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\player\Player;

class PistonHeadBlock extends Transparent implements AnyFacing {
    use AnyFacingTrait;

    public function isSticky(): bool {
        return false;
    }

    public function isSolid(): bool {
        return true;
    }

    public function getDropsForCompatibleTool(Item $item): array {
        return [];
    }

    public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []): bool {
        $world = $this->position->getWorld();
        $basePos = $this->position->getSide(Facing::opposite($this->facing));
        $base = $world->getBlock($basePos);

        $result = parent::onBreak($item, $player, $returnedItems);
        if ($base instanceof PistonBlock && $base->getFacing() === $this->facing) {
            $world->useBreakOn($basePos, $item, $player, false, $returnedItems);
        }

        return $result;
    }

    public function onNearbyBlockChange(): void {
        $base = $this->position->getWorld()->getBlock($this->position->getSide(Facing::opposite($this->facing)));
        if (!($base instanceof PistonBlock) || $base->getFacing() !== $this->facing) {
            $this->position->getWorld()->setBlock($this->position, VanillaBlocks::AIR());
        }
    }

    protected function recalculateCollisionBoxes(): array {
        $axis = Facing::axis($this->facing);

        $rod = AxisAlignedBB::one();
        foreach ([Axis::X, Axis::Y, Axis::Z] as $candidate) {
            if ($candidate === $axis) {
                continue;
            }
            $rod->squash($candidate, 6 / 16);
        }

        $head = AxisAlignedBB::one()->trim(Facing::opposite($this->facing), 12 / 16);
        return [$rod, $head];
    }
}
