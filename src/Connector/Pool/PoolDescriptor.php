<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Pool;

use PDO;
use Swoole\ConnectionPool;
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOProxy;
use Tabula17\Satelles\Nexus\Utilis\Connector\Status;
use Tabula17\Satelles\Nexus\Utilis\Exception\RuntimeException;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;
use Tabula17\Satelles\Utilis\Config\ConnectionConfig;

class PoolDescriptor extends AbstractDescriptor
{
    protected(set) string $name;
    protected(set) ?string $id;
    protected(set) ConnectionConfig $config
        {
            set(ConnectionConfig $config) {
                $this->config = $config;
                $class = $this->poolClass ?? ConnectionPool::class;
                if (str_contains($this->poolClass, 'PDO')) {
                    $pdoConfig = new PDOConfig()
                        ->withDriver($config->driver->value)
                        ->withHost($config->host);
                    if (isset($config->port)) {
                        $pdoConfig->withPort($config->port);
                    }
                    if (isset($config->dbname)) {
                        $pdoConfig->withDbname($config->dbname);
                    }
                    if (isset($config->username)) {
                        $pdoConfig->withUsername($config->username);
                    }
                    if (isset($config->password)) {
                        $pdoConfig->withPassword($config->password);
                    }
                    if (isset($config->charset)) {
                        $pdoConfig->withCharset($config->charset);
                    }
                    if (isset($config->options)) {
                        $pdoConfig->withOptions($config->options);
                    }
                    $this->pool = new $class($pdoConfig, $this->poolSize);
                } else {
                    $this->pool = new $class([$config, 'tcpConnector'], $this->poolSize);
                }
                $this->name = $config->name;
            }
        }
    private(set) ConnectionPool $pool
        {
            set(ConnectionPool $pool) {
                if (!isset($this->config)) {
                    throw new RuntimeException("Cannot set pool without config");
                }
                $this->pool = $pool;
            }
        }
    protected(set) Status $status;
    protected(set) int $poolSize = 3;
    private(set) int $used = 0;

    public ?string $lastError = null;
    public ?float $lastErrorAt = null;
    protected(set) int $failedAttempts = 0;
    protected(set) int $maxFailedAttempts;

    public function __construct(ConnectionConfig $config, int $poolSize = 3, int $maxFailedAttempts = 3, ?string $id = null, private readonly string $poolClass = ConnectionPool::class)
    {
        $this->id = $id;
        $this->poolSize = $poolSize;
        $this->config = $config;
        $this->status = Status::EMPTY;
        $this->maxFailedAttempts = $maxFailedAttempts;
        parent::__construct();
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setPoolSize(int $poolSize): void
    {
        $this->poolSize = $poolSize;
    }

    public function setStatus(Status $status): void
    {
        $this->status = $status;
    }

    public function getPool(): ConnectionPool
    {
        return $this->pool;
    }

    public function getConnection(): false|PDO|PDOProxy
    {
        if ($this->status->isEmpty()) {
            trigger_error("Pool is empty. Fill it before using it.", E_USER_WARNING);
            return false;
        }

        if ($this->status->hasFailure()) {
            trigger_error("Pool is unreachable. Check lastError and lastErrorAt properties.", E_USER_WARNING);
            return false;
        }
        $this->used++;
        $this->setIfActive();
        try {
            return $this->pool->get();
        } catch (\Throwable $e) {
            $this->used--;
            $this->setIfActive();
            trigger_error("Failed to get connection: " . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    public function release(PDO|PDOProxy $connection): void
    {
        $this->used--;
        $this->setIfActive();
        $this->pool->put($connection);
    }

    public function getUsed(): int
    {
        return $this->used;
    }

    public function available(): bool
    {
        return $this->used < $this->poolSize;
    }

    public function fill(): void
    {
        $this->pool->fill();
        $this->status = Status::READY;
        $this->used = 0;
    }

    public function close(): void
    {
        try {
            $this->pool?->close();
        } catch (\Throwable $e) { //in case of swoole pool it throws an exception if the pool is already closed, but in case of other pool implementations it may not throw an exception. So we catch any throwable to be safe.
            trigger_error("Failed to close pool, maybe already closed: " . $e->getMessage(), E_USER_NOTICE);
        }
        $this->status = Status::EMPTY;
        $this->used = 0;
    }

    public function canConnect(): bool
    {
        $status = $this->config->canConnect();
        if ($status === false) {
            $this->failedAttempts++;
            $this->lastError = $this->config->lastConnectionError;
            $this->lastErrorAt = microtime(true);
            $this->status = Status::UNREACHABLE;
            $this->pool->close();
        } else {
            if ($this->status->hasFailure()) {
                $this->resetFailedAttempts();
                $this->fill();
            }
            $this->setIfActive();
        }
        return $status;
    }

    private function setIfActive(): void
    {
        if ($this->status->hasFailure()) {
            return;
        }
        if ($this->available()) {
            $this->status = $this->used === 0 ? Status::READY : Status::ACTIVE;
        } else {
            $this->status = Status::FULL;
        }
    }

    public function resetFailedAttempts(): void
    {
        $this->failedAttempts = 0;
        $this->lastError = null;
        $this->lastErrorAt = null;
    }

    public function canRetry(): bool
    {
        // -1 means infinite retries, 0 means no retries
        return $this->maxFailedAttempts < 0 || $this->failedAttempts < $this->maxFailedAttempts;
    }

    public function recreate(): static
    {
        /*
    public function __construct(
        ConnectionConfig $config, int $poolSize = 3,
        int $maxFailedAttempts = 3, int $id = 0,
         private readonly string $poolClass = ConnectionPool::class)
    {
        $this->id = $id;
        $this->poolSize = $poolSize;
        $this->config = $config;
        $this->status = Status::EMPTY;
        $this->maxFailedAttempts = $maxFailedAttempts;
        parent::__construct();
    }
         */
        return new static($this->config, $this->poolSize, $this->maxFailedAttempts, null, $this->poolClass);
    }
}