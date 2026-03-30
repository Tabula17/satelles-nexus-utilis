<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Pool;

use Swoole\Database\PDOProxy;
use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class PdoCollection extends GenericCollection
{

    protected string $type = PoolDescriptor::class;
    protected string $poolCollectionId;
    protected(set) int $maxPoolInstances = 10;


    public function __construct(PoolDescriptor ...$pdoPool)
    {
        //$this->values = $pdoPool;
        foreach ($pdoPool as $pdo) {
            $this->loadPool($pdo);
        }
        $this->poolCollectionId = uniqid('pool:pdo:', false);
    }

    public function loadPool(PoolDescriptor $pool): int
    {
        $pool->setId($this->nextPoolId($pool->name));
        $this->offsetSet($pool->id, $pool);
        return $pool->id;
    }

    public function add(mixed $value): void
    {
        if ($value instanceof PoolDescriptor) {
            $this->loadPool($value);
        } else {
            trigger_error("Invalid type for PdoCollection: " . gettype($value), E_USER_WARNING);
        }
    }

    public function countByPool(string $poolName): int
    {
        $pools = $this->filter(static fn(PoolDescriptor $descriptor) => $descriptor->name === $poolName);
        return count($pools);
    }

    public function collect(string $key): array
    {
        return array_filter(array_map(static fn(PoolDescriptor $config) => $config->$key, $this->values));
    }

    public function nextPoolId(string $poolName): string
    {
        $count = $this->countByPool($poolName);
        return "$poolName:$count";
    }

    public function getAvailablePools(): static
    {
        return $this->filter(static fn(PoolDescriptor $descriptor) => !$descriptor->status->hasFailure());
    }

    public function getUnreachablePools(): static
    {
        return $this->filter(static fn(PoolDescriptor $descriptor) => $descriptor->status->hasFailure());
    }

    public function getFailedPools(): static
    {
        return $this->filter(static fn(PoolDescriptor $descriptor) => $descriptor->status->hasFailure() && !$descriptor->canRetry());
    }

    public function resetFailedPools(): void
    {
        foreach ($this->getFailedPools() as $pool) {
            $pool->resetFailedAttempts();
        }
    }

    public function getPoolById(string $poolId): PoolDescriptor
    {
        return $this->find(static fn(PoolDescriptor $descriptor) => $descriptor->id === $poolId);
    }

    public function getPoolDescriptor(string $poolName): PoolDescriptor
    {
        return $this->find(static fn(PoolDescriptor $descriptor) => $descriptor->name === $poolName);
    }

    public function getPoolsByName(string $poolName): static
    {
        return $this->filter(static fn(PoolDescriptor $descriptor) => $descriptor->name === $poolName);
    }

    public function getConnection(string $poolName): array //?PDOProxy
    {
        $pools = $this->getPoolsByName($poolName);
        $connection = null;
        $poolId = null;
        foreach ($pools as $descriptor) {
            if ($descriptor->available()) {
                $poolId = $descriptor->id;
                $connection = $descriptor->getConnection();
                break;
            }
        }
        if ($connection === null && $pools->count() < $this->maxPoolInstances) {
            $poolId = $this->loadPool(new PoolDescriptor($this->getPoolDescriptor($poolName)->config));
            $pool = $this->getPoolById($poolId);
            $pool->fill();
            $connection = $pool->getConnection();
        }
        return isset($connection, $poolId) ? [$connection, $poolId] : [null, null];
    }

    public function releaseConnection(PDOProxy $connection, string $poolId): void
    {
        $this->getPoolById($poolId)->release($connection);
    }

    public function removePool(string $poolName, int $reduceTo = 0): void
    {
        $poolCount = $this->countByPool($poolName);
        if ($poolCount > 1) {
            while ($poolCount > $reduceTo) {
                $this->remove($this->getPoolDescriptor($poolName));
                $poolCount--;
            }
        } elseif ($reduceTo < 1) {
            $this->remove($this->getPoolDescriptor($poolName));
        }
    }

    public function fill(): void
    {
        foreach ($this as $pool) {
            $pool->fill();
        }
    }

    public function close(): void
    {
        foreach ($this as $pool) {
            $pool->close();
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}