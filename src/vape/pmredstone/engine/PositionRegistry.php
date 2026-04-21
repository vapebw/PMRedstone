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

namespace vape\pmredstone\engine;

use pocketmine\world\Position;

final class PositionRegistry {

    /** @var array<int, array<string, array{int, int, int}>> worldId => key => [x, y, z] */
    private array $sensors = [];

    /** @var array<int, array<string, array{int, int, int}>> worldId => key => [x, y, z] */
    private array $plates = [];

    public function registerSensor(Position $pos): void {
        $wid = $pos->getWorld()->getId();
        $key = $this->key((int) $pos->x, (int) $pos->y, (int) $pos->z);
        $this->sensors[$wid][$key] = [(int) $pos->x, (int) $pos->y, (int) $pos->z];
    }

    public function unregisterSensor(Position $pos): void {
        $wid = $pos->getWorld()->getId();
        $key = $this->key((int) $pos->x, (int) $pos->y, (int) $pos->z);
        unset($this->sensors[$wid][$key]);
        if (isset($this->sensors[$wid]) && count($this->sensors[$wid]) === 0) {
            unset($this->sensors[$wid]);
        }
    }

    public function registerPlate(Position $pos): void {
        $wid = $pos->getWorld()->getId();
        $key = $this->key((int) $pos->x, (int) $pos->y, (int) $pos->z);
        $this->plates[$wid][$key] = [(int) $pos->x, (int) $pos->y, (int) $pos->z];
    }

    public function unregisterPlate(Position $pos): void {
        $wid = $pos->getWorld()->getId();
        $key = $this->key((int) $pos->x, (int) $pos->y, (int) $pos->z);
        unset($this->plates[$wid][$key]);
        if (isset($this->plates[$wid]) && count($this->plates[$wid]) === 0) {
            unset($this->plates[$wid]);
        }
    }

    /** @return array<string, array{int, int, int}> */
    public function getSensorsForWorld(int $worldId): array {
        return $this->sensors[$worldId] ?? [];
    }

    /** @return array<string, array{int, int, int}> */
    public function getPlatesForWorld(int $worldId): array {
        return $this->plates[$worldId] ?? [];
    }

    public function invalidateWorld(int $worldId): void {
        unset($this->sensors[$worldId], $this->plates[$worldId]);
    }

    public function getSensorCount(): int {
        $total = 0;
        foreach ($this->sensors as $set) {
            $total += count($set);
        }
        return $total;
    }

    public function getPlateCount(): int {
        $total = 0;
        foreach ($this->plates as $set) {
            $total += count($set);
        }
        return $total;
    }

    private function key(int $x, int $y, int $z): string {
        return "$x:$y:$z";
    }
}
