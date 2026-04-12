<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Trait;

use Swoole\Timer;
use Tabula17\Satelles\Nexus\Utilis\Server\Pars\TictacusCollection;

trait CronosTrait
{
    final public const bool CRONOS_ENABLED = true;
    private TictacusCollection $managedTasks;

    public function getManagedTasks(): TictacusCollection
    {
        return $this->managedTasks;
    }

    public function addTick(int $interval, callable $callback, ...$properties): int
    {
        if (!isset($this->managedTasks)) {
            $this->managedTasks = new TictacusCollection();
        }
        $wrappedCallback = function (...$args) use ($callback) {
            try {
                $callback(...$args);
            } catch (\Throwable $e) {
                $this->logger?->error("Error en timer: " . $e->getMessage());
                $this->logger?->debug("Stack trace: " . $e->getTraceAsString());
            }
        };
        $properties = $properties ?? [];
        if (!isset($properties['owner'])) {
            $class = explode('\\', get_class($this));
            $properties['owner'] = end($class);
        }
        $properties['interval'] = $interval;
        $properties['added'] = microtime(true);
        $properties['id'] = Timer::tick($interval, $wrappedCallback, $properties);
        $this->managedTasks->offsetSet($properties['id'], $properties);

        $this->logger?->debug("⏱️ Timer agregado: " . $properties['owner'] . " - Intervalo: " . $interval / 1000 . " segundos | ID: " . $properties['id'] . " | Total timers: " . count($this->managedTasks) . " | Params: " . json_encode($properties) . "");

        return $properties['id'];
    }

    public function removeTick(int $id): bool
    {
        $this->managedTasks->offsetUnset($id);
        return Timer::clear($id);
    }

    public function removeTicksByOwner(string $owner): void
    {
        $tasksToRemove = $this->managedTasks->getForOwner($owner);
        foreach ($tasksToRemove as $id => $task) {
            Timer::clear($id);
            $this->managedTasks->offsetUnset($id);
        }
    }

    public function removeAllTicks(): void
    {
        $this->managedTasks->clear();
        Timer::clearAll();
    }

    public function addTimer(int $delay, callable $callback, ...$properties): int
    {
        $properties = $properties ?? [];
        return Timer::after($delay, $callback, $properties);

    }
}