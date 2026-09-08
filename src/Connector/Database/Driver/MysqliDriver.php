<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database\Driver;

use mysqli;
use mysqli_stmt;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DbConfig;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DriverInterface;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DriversEnum;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\OperationsEnum;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\Result\MysqliResult;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\ResultInterface;
use Tabula17\Satelles\Nexus\Utilis\Exception\InvalidArgumentException;
use Tabula17\Satelles\Utilis\Collection\DataModelCollection;

class MysqliDriver implements DriverInterface
{

    private mysqli $connection;
    private mysqli_stmt $stmt;

    public function __construct(
        private DbConfig                     $config,
        private readonly DataModelCollection $dataModel = new DataModelCollection(),
        //private readonly bool                $unbuffered = false,
        private bool                         $autoCommit = true

    )
    {
        if($this->config->driver !== DriversEnum::MYSQL) {
            throw new \InvalidArgumentException("Invalid driver type for MysqliDriver. Expected 'mysqli', got '{$this->config->driver}'.");
        }
        $this->config->set('usePdo', false);
        $this->connection = $this->config->getConnector();

    }
    public function query(string $sql, array $bindings = [], OperationsEnum $operation = OperationsEnum::SELECT): MysqliResult
    {
        if (!$this->config->canConnect()) {
            throw new InvalidArgumentException("The database connection is not established. Please check your configuration and ensure that the connection is valid.");
        }
        if (!$operation->canHaveResultset()) {
            throw new InvalidArgumentException("The operation type '{$operation->name}' is not valid for a query that returns a result set. Use execute() for non-result set operations.");
        }
        $this->connection->autocommit($this->autoCommit);
        $this->stmt = $this->connection->prepare($sql);
        mysqli_stmt_bind_param($this->stmt, str_repeat('s', count($bindings)), ...$bindings);
        $this->stmt->execute($bindings);
        return new MysqliResult($this->stmt, $this->dataModel);
    }

    public function execute(string $sql, array $bindings = [], OperationsEnum $operation = OperationsEnum::EXECUTE): bool
    {
        if (!$this->config->canConnect()) {
            throw new InvalidArgumentException("The database connection is not established. Please check your configuration and ensure that the connection is valid.");
        }
        if($operation->canHaveResultset()) {
            trigger_error("Executing a query that can return a result set using execute() is not recommended. Use query() instead.", E_USER_WARNING);
        }
        $this->connection->autocommit($this->autoCommit);
        $this->stmt = $this->connection->prepare($sql);
        return $this->stmt->execute($bindings);
    }

    public function lastInsertId(): mixed
    {
        return $this->connection->insert_id;
    }

    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollback(): void
    {
        $this->connection->rollback();
    }

    public function affectedRows(): int
    {
        return $this->connection->affected_rows;
    }
}