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

namespace vape\pmredstone\tile;

use pocketmine\block\tile\Spawnable;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;

class PistonArmTile extends Spawnable {

    private float $progress = 0.0;
    private float $lastProgress = 0.0;
    private int $state = 0;
    private int $newState = 0;
    private bool $sticky = false;

    public function readSaveData(CompoundTag $nbt): void {
        $this->progress = $nbt->getFloat("Progress", 0.0);
        $this->lastProgress = $nbt->getFloat("LastProgress", 0.0);
        $this->state = $nbt->getByte("State", 0);
        $this->newState = $nbt->getByte("NewState", 0);
        $this->sticky = $nbt->getByte("Sticky", 0) !== 0;
    }

    protected function writeSaveData(CompoundTag $nbt): void {
        $nbt->setFloat("Progress", $this->progress);
        $nbt->setFloat("LastProgress", $this->lastProgress);
        $nbt->setByte("State", $this->state);
        $nbt->setByte("NewState", $this->newState);
        $nbt->setByte("Sticky", $this->sticky ? 1 : 0);
    }

    protected function addAdditionalSpawnData(CompoundTag $nbt, TypeConverter $typeConverter): void {
        $nbt->setFloat("Progress", $this->progress);
        $nbt->setFloat("LastProgress", $this->lastProgress);
        $nbt->setByte("State", $this->state);
        $nbt->setByte("NewState", $this->newState);
        $nbt->setByte("Sticky", $this->sticky ? 1 : 0);
    }

    public function getProgress(): float {
        return $this->progress;
    }

    public function setProgress(float $progress): void {
        $this->progress = $progress;
    }

    public function getLastProgress(): float {
        return $this->lastProgress;
    }

    public function setLastProgress(float $lastProgress): void {
        $this->lastProgress = $lastProgress;
    }

    public function getState(): int {
        return $this->state;
    }

    public function setState(int $state): void {
        $this->state = $state;
    }

    public function getNewState(): int {
        return $this->newState;
    }

    public function setNewState(int $newState): void {
        $this->newState = $newState;
    }

    public function isSticky(): bool {
        return $this->sticky;
    }

    public function setSticky(bool $sticky): void {
        $this->sticky = $sticky;
    }
}
