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

namespace vape\pmredstone;

use pocketmine\plugin\PluginBase;
use vape\pmredstone\block\PistonBlockRegistry;
use vape\pmredstone\config\RedstoneConfig;
use vape\pmredstone\engine\RedstoneEngine;
use vape\pmredstone\listener\RedstoneListener;
use vape\pmredstone\scheduler\ButtonSyncTask;
use vape\pmredstone\scheduler\DaylightSensorTask;
use vape\pmredstone\scheduler\PressurePlateTask;
use pocketmine\block\tile\TileFactory;
use vape\pmredstone\tile\PistonArmTile;
use vape\pmredstone\scheduler\RedstoneTickTask;

final class Loader extends PluginBase
{

    private static self $instance;

    private RedstoneConfig $redstoneConfig;
    private RedstoneEngine $engine;

    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function onEnable(): void
    {
        self::$instance = $this;

        $this->saveDefaultConfig();
        $this->redstoneConfig = new RedstoneConfig($this->getConfig());
        PistonBlockRegistry::bootstrap();
        TileFactory::getInstance()->register(PistonArmTile::class, ["PistonArm", "minecraft:piston_arm"]);

        $this->engine = new RedstoneEngine($this, $this->redstoneConfig);

        $pm = $this->getServer()->getPluginManager();
        $pm->registerEvents(new RedstoneListener($this->engine, $this->redstoneConfig), $this);

        $this->getScheduler()->scheduleRepeatingTask(
            new RedstoneTickTask($this->engine),
            $this->redstoneConfig->getTickRate()
        );

        if ($this->redstoneConfig->isDaylightSensorEnabled()) {
            $this->getScheduler()->scheduleRepeatingTask(
                new DaylightSensorTask($this->engine, $this->redstoneConfig),
                $this->redstoneConfig->getDaylightUpdateInterval()
            );
        }

        if ($this->redstoneConfig->isPressurePlateEnabled()) {
            $this->getScheduler()->scheduleRepeatingTask(
                new PressurePlateTask($this->engine, $this->redstoneConfig),
                $this->redstoneConfig->getPressurePlateCheckInterval()
            );
        }

        $this->getScheduler()->scheduleRepeatingTask(
            new ButtonSyncTask($this->engine, $this->redstoneConfig),
            1
        );

        $cfg = $this->redstoneConfig;
        $this->getLogger()->info("PMRedstone" . $this->getDescription()->getVersion() . " enabled.");
        $this->getLogger()->info(sprintf(
            "Budget: %d updates/tick | Queue cap: %d | Tick rate: %d",
            $cfg->getMaxUpdateBudget(),
            $cfg->getMaxQueueSize(),
            $cfg->getTickRate()
        ));

        if ($cfg->isDebugEnabled()) {
            $this->getLogger()->warning("Debug mode is ON - disable in production.");
        }

        if ($cfg->isPistonsEnabled()) {
            $this->getLogger()->debug("PMRedstone piston support registered custom plugin piston blocks.");
        }
    }

    public function onDisable(): void
    {
        $this->engine->shutdown();
    }

    public function getEngine(): RedstoneEngine
    {
        return $this->engine;
    }

    public function getRedstoneConfig(): RedstoneConfig
    {
        return $this->redstoneConfig;
    }
}
