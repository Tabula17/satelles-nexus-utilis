<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector;


use Swoole\ConnectionPool;
use Swoole\Database\PDOProxy;
use Tabula17\Satelles\Nexus\Utilis\Connector\Pool\PoolCollection;
use Tabula17\Satelles\Nexus\Utilis\Connector\Pool\PoolDescriptor;
use Tabula17\Satelles\Utilis\Collection\ConnectionCollection;
use Tabula17\Satelles\Utilis\Config\ConnectionConfig;

interface CoetusNexuumInterface
{
    public function loadConnection(ConnectionConfig $config, int $poolSize = 3, string $poolClass = ConnectionPool::class): void;

    public function loadConnections(ConnectionCollection $configs, int $poolSize = 3, string $poolClass = ConnectionPool::class): void;

    public function getPools(string $poolName): ?PoolCollection;

    public function getPool(string $poolName): ?PoolDescriptor;

    public function getPoolById(string $poolId): ?PoolDescriptor;

    public function getAvailablePoolNames(): array;

    public function getAvailablePools(): PoolCollection;

    public function getUnreachablePools(): PoolCollection;

    public function getFailedPools(): PoolCollection;

    public function getPoolNames(): array;

    public function getConnection(string $poolName): mixed;

    public function releaseConnection(mixed $connection): void;

    public function checkPoolHealth(): array;

    public function getPoolStats(): array;

    public function getPoolStatByConn(mixed $connection): array;

    public function closePools(): void;
}