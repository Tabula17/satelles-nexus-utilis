<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Trait;

use Swoole\Timer;
use Tabula17\Satelles\Nexus\Utilis\Server\Pars\TictacusCollection;

trait CronosTrait
{
    private TictacusCollection $tasks;

    public function getTasks(): TictacusCollection
    {
        return $this->tasks;
    }

    public function addTick(int $interval, callable $callback, ...$properties): int
    {
        $properties = $properties ?? [];
        if (!isset($properties['owner'])) {
            $class = explode('\\', get_class($this));
            $properties['owner'] = end($class);
        }
        $properties['interval'] = $interval;
        $properties['added'] = microtime(true);

        $id = Timer::tick($interval, $callback, ...$properties);
        $this->tasks->offsetSet($id, $properties);
        return $id;
    }
    public function removeTick(int $id): bool
    {
        $this->tasks->offsetUnset($id);
        return Timer::clear($id);
    }
    public function removeTicksByOwner(string $owner): void
    {
        $tasksToRemove = $this->tasks->getForOwner($owner);
        foreach ($tasksToRemove as $id => $task) {
            Timer::clear($id);
            $this->tasks->offsetUnset($id);
        }
    }
    public function removeAllTicks(): void
    {
        $this->tasks->clear();
        Timer::clearAll();
    }
    public function addTimer(int $delay, callable $callback, ...$properties): int
    {
        $properties = $properties ?? [];
        return Timer::after($delay, $callback, ...$properties);

    }
}