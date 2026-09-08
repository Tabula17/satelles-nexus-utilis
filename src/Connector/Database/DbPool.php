<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

use mysqli;
use PDO;
use Swoole\Coroutine\Channel;
use Throwable;

class DbPool
{
    private Channel $pool;
    private(set) int $maxConnections;

    private(set) int $available = 0;

    public function __construct(private readonly DbConfig $dbConfig, int $maxConnections = 10)
    {
        $this->maxConnections = $maxConnections;
        $this->pool = new Channel($maxConnections);
    }

    public function fill(): void
    {
        for ($i = 0; $i < $this->maxConnections; $i++) {
            try {
                $connector = $this->dbConfig->copy();
                $connector->getConnector();
                $this->pool->push($connector);
                $this->available++;
            } catch (Throwable $exception) {

            }
        }
    }

    public function pop()
    {
        $conn = $this->pool->pop();
        if ($conn && $this->available > 0) {
            $this->available--;
        }
        return $conn;

    }

    public function push(mixed $connection): bool
    {
        $success = $this->pool->push($connection);
        if ($success) {
            $this->available++;
        }
        return $success;
    }

    public function clear(): void
    {
        $this->pool = new Channel($this->maxConnections);
    }

    public function withMaxConnections(int $maxConnections): self
    {
        return new self($this->dbConfig, $maxConnections);
    }

}