<?php

declare(strict_types=1);

namespace vape\pmredstone\block;

final class StickyPistonBlock extends PistonBlock {
    public function isSticky(): bool {
        return true;
    }
}
