<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

use Swoole\Coroutine\Channel;
use Throwable;

class DbPool
{
    private Channel $pool;
    private(set) int $size;

    private(set) int $available = 0;
    private(set) int $used = 0;
    private(set) PoolStatusEnum $status = PoolStatusEnum::EMPTY;

    public function __construct(private readonly DbConfig $dbConfig, int $size = 10)
    {
        $this->size = $size;
        $this->pool = new Channel($size);
    }

    public function fill(): void
    {
        $this->clear();
        $this->available = 0;
        $this->used = 0;
        if (!$this->dbConfig->canConnect()) {
            $this->status = PoolStatusEnum::UNREACHABLE;
            return;
        }
        $err = 0;
        for ($i = 0; $i < $this->size; $i++) {
            try {
                $connector = $this->dbConfig->copy();
                $connector->getConnector();
                $this->pool->push($connector);
                $this->available++;
            } catch (Throwable $exception) {
                $err++;
            }
        }
        $this->status = $err > 0 ? PoolStatusEnum::ERROR : PoolStatusEnum::OK;

    }

    public function pop()
    {
        $conn = $this->pool->pop();
        if ($conn && $this->available > 0) {
            $this->available--;
            $this->used++;
        }
        $this->status = $this->used === $this->size ? PoolStatusEnum::BUSY : PoolStatusEnum::OK;
        return $conn;

    }

    public function push(mixed $connection): bool
    {
        $success = $this->pool->push($connection);
        if ($success) {
            $this->available++;
            $this->used--;
        }
        $this->status = PoolStatusEnum::OK;
        return $success;
    }

    public function clear(): void
    {
        $this->pool = new Channel($this->size);
        $this->available = 0;
        $this->used = 0;
        $this->status = PoolStatusEnum::EMPTY;
    }

    public function withSize(int $size): self
    {
        return new self($this->dbConfig, $size);
    }

    public function isFull(): bool
    {
        return $this->available === $this->size;
    }

    public function isEmpty(): bool
    {
        return $this->available === 0 && $this->used === 0;
    }

    public function available(): int
    {
        return $this->available;
    }

    public function name(): string
    {
        return $this->dbConfig->name ?? basename($this->dbConfig::class) . spl_object_id($this);
    }
}