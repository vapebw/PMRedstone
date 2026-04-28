<?php

declare(strict_types=1);

namespace vape\pmredstone\block;

final class StickyPistonHeadBlock extends PistonHeadBlock {
    public function isSticky(): bool {
        return true;
    }
}
