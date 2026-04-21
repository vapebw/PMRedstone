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

namespace vape\pmredstone\config;

use pocketmine\utils\Config;

final class RedstoneConfig {

    private bool $enabled;
    private int $tickRate;
    private int $maxUpdateBudget;
    private int $maxQueueSize;
    private array $disabledWorlds;

    private bool $pistonsEnabled;
    private int $pistonMaxPush;
    private int $pistonPushDelay;
    private int $pistonRetractDelay;
    private bool $stickyRetract;
    private array $pistonDisabledWorlds;

    private bool $dispenserEnabled;
    private int $dispenserFireDelay;

    private bool $dropperEnabled;
    private int $dropperDropDelay;

    private bool $observerEnabled;
    private int $observerPulseDuration;

    private bool $daylightEnabled;
    private int $daylightUpdateInterval;

    private bool $repeaterEnabled;
    private int $repeaterDefaultDelay;

    private bool $comparatorEnabled;

    private bool $pressurePlateEnabled;
    private int $pressurePlateCheckInterval;

    private bool $debugEnabled;
    private bool $debugPropagation;
    private bool $debugPiston;
    private bool $debugPowerChanges;

    public function __construct(Config $config) {
        $this->enabled             = (bool)  $config->getNested("redstone.enabled", true);
        $this->tickRate            = max(1,   (int) $config->getNested("redstone.tick-rate", 2));
        $this->maxUpdateBudget     = max(16,  (int) $config->getNested("redstone.max-update-budget", 256));
        $this->maxQueueSize        = max(64,  (int) $config->getNested("redstone.max-update-queue", 8192));
        $this->disabledWorlds      = (array)  $config->getNested("redstone.disabled-worlds", []);

        $this->pistonsEnabled           = (bool) $config->getNested("pistons.enabled", true);
        $this->pistonMaxPush            = max(1, min(12, (int) $config->getNested("pistons.max-push-distance", 12)));
        $this->pistonPushDelay          = max(1, (int) $config->getNested("pistons.push-delay-ticks", 2));
        $this->pistonRetractDelay       = max(1, (int) $config->getNested("pistons.retract-delay-ticks", 2));
        $this->stickyRetract            = (bool) $config->getNested("pistons.sticky-retract-on-power-loss", true);
        $this->pistonDisabledWorlds     = (array) $config->getNested("pistons.disabled-worlds", []);

        $this->dispenserEnabled    = (bool) $config->getNested("dispenser.enabled", true);
        $this->dispenserFireDelay  = max(1, (int) $config->getNested("dispenser.fire-delay-ticks", 4));

        $this->dropperEnabled      = (bool) $config->getNested("dropper.enabled", true);
        $this->dropperDropDelay    = max(1, (int) $config->getNested("dropper.drop-delay-ticks", 4));

        $this->observerEnabled        = (bool) $config->getNested("observer.enabled", true);
        $this->observerPulseDuration  = max(1, (int) $config->getNested("observer.pulse-duration-ticks", 2));

        $this->daylightEnabled          = (bool) $config->getNested("daylight-sensor.enabled", true);
        $this->daylightUpdateInterval   = max(1, (int) $config->getNested("daylight-sensor.update-interval", 20));

        $this->repeaterEnabled      = (bool) $config->getNested("repeater.enabled", true);
        $this->repeaterDefaultDelay = max(1, min(4, (int) $config->getNested("repeater.default-delay-ticks", 2)));

        $this->comparatorEnabled = (bool) $config->getNested("comparator.enabled", true);

        $this->pressurePlateEnabled       = (bool) $config->getNested("pressure-plate.enabled", true);
        $this->pressurePlateCheckInterval = max(1, (int) $config->getNested("pressure-plate.entity-check-interval", 4));

        $this->debugEnabled       = (bool) $config->getNested("debug.enabled", false);
        $this->debugPropagation   = (bool) $config->getNested("debug.log-propagation", false);
        $this->debugPiston        = (bool) $config->getNested("debug.log-piston-moves", false);
        $this->debugPowerChanges  = (bool) $config->getNested("debug.log-power-changes", false);
    }

    public function isEnabled(): bool { return $this->enabled; }
    public function getTickRate(): int { return $this->tickRate; }
    public function getMaxUpdateBudget(): int { return $this->maxUpdateBudget; }
    public function getMaxQueueSize(): int { return $this->maxQueueSize; }
    public function getDisabledWorlds(): array { return $this->disabledWorlds; }
    public function isWorldDisabled(string $name): bool { return in_array($name, $this->disabledWorlds, true); }

    public function isPistonsEnabled(): bool { return $this->pistonsEnabled; }
    public function getPistonMaxPush(): int { return $this->pistonMaxPush; }
    public function getPistonPushDelay(): int { return $this->pistonPushDelay; }
    public function getPistonRetractDelay(): int { return $this->pistonRetractDelay; }
    public function isStickyRetract(): bool { return $this->stickyRetract; }
    public function isPistonWorldDisabled(string $name): bool { return in_array($name, $this->pistonDisabledWorlds, true); }

    public function isDispenserEnabled(): bool { return $this->dispenserEnabled; }
    public function getDispenserFireDelay(): int { return $this->dispenserFireDelay; }

    public function isDropperEnabled(): bool { return $this->dropperEnabled; }
    public function getDropperDropDelay(): int { return $this->dropperDropDelay; }

    public function isObserverEnabled(): bool { return $this->observerEnabled; }
    public function getObserverPulseDuration(): int { return $this->observerPulseDuration; }

    public function isDaylightSensorEnabled(): bool { return $this->daylightEnabled; }
    public function getDaylightUpdateInterval(): int { return $this->daylightUpdateInterval; }

    public function isRepeaterEnabled(): bool { return $this->repeaterEnabled; }
    public function getRepeaterDefaultDelay(): int { return $this->repeaterDefaultDelay; }

    public function isComparatorEnabled(): bool { return $this->comparatorEnabled; }

    public function isPressurePlateEnabled(): bool { return $this->pressurePlateEnabled; }
    public function getPressurePlateCheckInterval(): int { return $this->pressurePlateCheckInterval; }

    public function isDebugEnabled(): bool { return $this->debugEnabled; }
    public function isDebugPropagation(): bool { return $this->debugPropagation; }
    public function isDebugPiston(): bool { return $this->debugPiston; }
    public function isDebugPowerChanges(): bool { return $this->debugPowerChanges; }
}
