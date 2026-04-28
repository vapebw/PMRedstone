<?php

declare(strict_types=1);

namespace vape\pmredstone\block;

use pocketmine\block\Transparent;
use pocketmine\item\Item;

final class MovingBlock extends Transparent {
    public function isSolid(): bool {
        return true;
    }

    public function getDropsForCompatibleTool(Item $item): array {
        return [];
    }
}
