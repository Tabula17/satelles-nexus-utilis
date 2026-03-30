<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Pool;

use PDO;
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool;
use Swoole\Database\PDOProxy;
use Tabula17\Satelles\Nexus\Utilis\Connector\Status;
use Tabula17\Satelles\Nexus\Utilis\Exception\RuntimeException;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;
use Tabula17\Satelles\Utilis\Config\ConnectionConfig;

class PoolDescriptor extends AbstractDescriptor
{
    protected(set) string $name;
    protected(set) string $id;
    protected(set) ConnectionConfig $config
        {
            set(ConnectionConfig $config) {
                $this->config = $config;
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
                $this->pool = new PDOPool($pdoConfig);
                $this->name = $config->name;
            }
        }
    private(set) PDOPool $pool
        {
            set(PDOPool $pool) {
                if (!isset($this->config)) {
                    throw new RuntimeException("Cannot set pool without config");
                }
                $this->pool = $pool;
            }
        }
    protected(set) Status $status;
    protected(set) int $poolSize;
    private(set) int $used = 0;

    public ?string $lastError = null;
    public ?float $lastErrorAt = null;
    protected(set) int $failedAttempts = 0;
    protected(set) int $maxFailedAttempts = 3;

    public function __construct(ConnectionConfig $config, int $poolSize = 3, int $id = 0)
    {
        $this->id = $id;
        $this->config = $config;
        $this->poolSize = $poolSize;
        $this->status = Status::EMPTY;
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

    public function getPool(): PDOPool
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
        $this->status = Status::INACTIVE;
    }
    public function close(): void
    {
        $this->pool?->close();
    }

    public function canConnect(): bool
    {
        $status = $this->config->canConnect();
        if ($status === false) {
            $this->failedAttempts++;
            $this->lastError = $this->config->lastConnectionError;
            $this->lastErrorAt = microtime(true);
            $this->status = Status::UNREACHABLE;
        } else {
            $this->status = Status::CONNECTED;
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
            $this->status = $this->used === 0 ? Status::INACTIVE : Status::ACTIVE;
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
        return $this->failedAttempts < $this->maxFailedAttempts;
    }
}