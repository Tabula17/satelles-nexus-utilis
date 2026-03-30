<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Pool;

use Psr\Log\LoggerInterface;
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOProxy;
use Tabula17\Satelles\Nexus\Utilis\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Nexus\Utilis\Exception\InvalidArgumentException;
use Tabula17\Satelles\Utilis\Collection\ConnectionCollection;
use Tabula17\Satelles\Utilis\Config\ConnectionConfig;
use Tabula17\Satelles\Utilis\Trait\CoroutineHelper;

class PoolConnectorManager
{
    use CoroutineHelper;
    private array $usedConnections = [];

    public function __construct(
        protected PdoCollection           $pools = new PdoCollection(),
        //   private readonly int     $maxPoolInstances = 3, <--- lo maneja PdoCollection
        private readonly float            $intervalRetry = 0.5,
        private readonly int              $maxRetries = 3,
        private readonly ?LoggerInterface $logger = null
    )
    {
    }

    public function loadConnection(ConnectionConfig $config, int $poolSize = 3): void
    {
        try {
            if (isset($config->maxConnections) && ($config->maxConnections > 0)) {
                $poolSize = $config->maxConnections;
            }
            if ($poolSize <= 0) {
                $this->logger?->error(ExceptionDefinitions::POOL_SIZE_GREATER_THAN_ZERO->value, $config->toArray());
                throw new InvalidArgumentException(ExceptionDefinitions::POOL_SIZE_GREATER_THAN_ZERO->value);
            }
            $this->logger?->info("Creating pool for $config->name");
            $this->logger?->debug("Connection delay: $config->dealy ms");
            $conn = new PoolDescriptor(
                config: $config,
                poolSize: $poolSize,
                maxFailedAttempts: $this->maxRetries,
            );
            $pool = $this->pools->getPoolById($this->pools->loadPool($conn));
            $canConnect = false;
            while (!$canConnect && $pool->canRetry()) {
                $canConnect = $pool->canConnect();
                $this->logger?->info("Attempt $pool->failedAttempts of $pool->maxFailedAttempts: Pool $config->name is unreachable, retrying in $this->intervalRetry seconds");
                $this->safeSleep($this->intervalRetry);
            }
            if ($canConnect) {
                $this->logger?->info("Pool $config->name is ready");
                $pool->fill();
            } else {
                $this->logger?->error("Pool $config->name is unreachable");
            }
        } catch (\Throwable $e) {
            $this->logger?->error("Failed to load connection: " . $e->getMessage(), $config->toArray());
        }
    }

    public function loadConnections(ConnectionCollection $configs): void
    {
        foreach ($configs as $config) {
            $this->loadConnection($config);
        }
    }

    public function getPools(string $poolName): ?PdoCollection
    {
        return $this->pools->getPoolsByName($poolName);
    }

    public function getPool(string $poolName): ?PoolDescriptor
    {
        return $this->pools->getPoolDescriptor($poolName);
    }

    public function getPoolById(string $poolId): ?PoolDescriptor
    {
        return $this->pools->getPoolById($poolId);
    }

    public function getAvailablePoolsByName(): array
    {
        return $this->pools->getAvailablePools()->collect('name');
    }

    public function getAvailablePools(): array
    {
        return $this->pools->getAvailablePools()->collect('id');
    }

    public function getUnreachablePools(): array
    {
        return $this->pools->getUnreachablePools()->collect('id');
    }

    public function getFailedPools(): array
    {
        return $this->pools->getFailedPools()->collect('id');
    }

    public function getConnection(string $poolName): ?PDOProxy
    {
        [$connection, $poolId] = $this->pools->getConnection($poolName);
        if (isset($connection, $poolId)) {
            $this->usedConnections[spl_object_id($connection)] = $poolId;
            return $connection;
        }
        return null;
    }

    public function releaseConnection(PDOProxy $connection): void
    {
        $this->pools->releaseConnection($connection, $this->usedConnections[spl_object_id($connection)]);
    }
}