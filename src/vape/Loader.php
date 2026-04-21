<?php

namespace vape;

use pocketmine\plugin\PluginBase;

class Loader extends PluginBase {
    public function onEnable(): void {
        $this->saveDefaultConfig();
    }
}