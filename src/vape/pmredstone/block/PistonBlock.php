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

    /*TODO: implement piston head also fix desync or idk what the fuck that is
    * Still not added cuz welp i think pmmp doesnt have piston head lololo
    * i hope any good contributor can do this UwU
    */
    use AnyFacingTrait;

    protected bool $extended = false;

    protected function describeBlockOnlyState(RuntimeDataDescriber $w): void
    {
        $w->facing(Facing::opposite($this->facing));
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
    private function resolvePlacementFacing(int $face, ?Player $player): int
    {
        if ($player === null) {
            return $face;
        }

        $pitch = $player->getLocation()->pitch;
        if ($pitch > 45) {
            return Facing::UP;
        } elseif ($pitch < -45) {
            return Facing::DOWN;
        }

        return Facing::opposite($player->getHorizontalFacing());
    }

    public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null): bool
    {
        $this->facing = $this->resolvePlacementFacing($face, $player);
        $this->extended = false;

        $pos = $blockReplace->getPosition();
        Loader::getInstance()->getLogger()->debug(sprintf(
            "[Piston][PLACE] %d,%d,%d facing=%s",
            $pos->getFloorX(),
            $pos->getFloorY(),
            $pos->getFloorZ(),
            self::facingName($this->facing)
        ));

        return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
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
