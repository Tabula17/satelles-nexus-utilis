<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

use Swoole\Coroutine\Channel;
use Throwable;

/**
 * A class representing a database connection pool, which manages the allocation
 * and deallocation of database connections to provide efficient reuse of resources.
 */
class DbPool
{
    private Channel $pool;
    private(set) int $size;

    private(set) int $available = 0;
    private(set) int $used = 0;
    private(set) PoolStatusEnum $status = PoolStatusEnum::EMPTY;

    /**
     * Constructor method for initializing the object with database configuration and pool size.
     *
     * @param DbConfig $dbConfig The configuration object for database connection.
     * @param int $size The size of the channel pool. Defaults to 10.
     * @return void
     */
    public function __construct(private readonly DbConfig $dbConfig, int $size = 10)
    {
        $this->size = $size;
        $this->pool = new Channel($size);
    }

    /**
     * Fills the connection pool by initializing connectors and adding them to the pool.
     * Resets the available and used counters, and updates the pool's status based on connectivity.
     *
     * @return void
     */
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

    /**
     * Retrieves a connection from the pool and updates the usage statistics and status.
     *
     * @return mixed The connection object retrieved from the pool, or null if no connection is available.
     */
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

    /**
     * Pushes a connection into the pool and updates the pool's internal state.
     *
     * @param mixed $connection The connection instance to be added to the pool.
     * @return bool Returns true if the connection was successfully pushed into the pool, otherwise false.
     */
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

    /**
     * Clears the current state of the pool by resetting its attributes and reinitializing the channel.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->pool = new Channel($this->size);
        $this->available = 0;
        $this->used = 0;
        $this->status = PoolStatusEnum::EMPTY;
    }

    /**
     * Creates a new instance with the specified pool size.
     *
     * @param int $size The desired size for the channel pool.
     * @return self A new instance with the updated pool size.
     */
    public function withSize(int $size): self
    {
        return new self($this->dbConfig, $size);
    }

    /**
     * Determines whether the current state is full, based on the comparison
     * between available capacity and the total size.
     *
     * @return bool Returns true if the available capacity equals the total size, false otherwise.
     */
    public function isFull(): bool
    {
        return $this->available === $this->size;
    }

    /**
     * Checks whether the pool is empty.
     *
     * @return bool True if both available and used resources are zero, false otherwise.
     */
    public function isEmpty(): bool
    {
        return $this->available === 0 && $this->used === 0;
    }

    /**
     * Retrieves the number of available resources.
     *
     * @return int The count of available resources.
     */
    public function available(): int
    {
        return $this->available;
    }

    /**
     * Retrieves the name associated with the database configuration.
     *
     * @return string The name of the database configuration or a generated unique identifier if the name is not set.
     */
    public function name(): string
    {
        return $this->dbConfig->name ?? basename($this->dbConfig::class) . spl_object_id($this);
    }
}