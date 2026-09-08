<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database\Driver;

use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DbConfig;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DriverInterface;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DriversEnum;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\OperationsEnum;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\Result\SqlSrvResult;
use Tabula17\Satelles\Nexus\Utilis\Exception\InvalidArgumentException;
use Tabula17\Satelles\Nexus\Utilis\Exception\RuntimeException;
use Tabula17\Satelles\Utilis\Collection\DataModelCollection;

class SqlSrvDriver implements DriverInterface
{
    private mixed $connection;
    private mixed $lastInsertId;
    private mixed $stmt;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        private DbConfig                     $config,
        private readonly DataModelCollection $dataModel = new DataModelCollection(),
        //private readonly bool                $unbuffered = false,
        private bool                         $autoCommit = true

    )
    {
        if ($this->config->driver !== DriversEnum::SQLSRV) {
            throw new \InvalidArgumentException("Invalid driver type for SqlSrvDriver. Expected 'sqlsrv', got '{$this->config->driver}'.");
        }
        $this->config->set('usePdo', false);
        $this->connection = $this->config->getConnector();

    }

    /**
     * @throws InvalidArgumentException
     */
    public function query(string $sql, array $bindings = [], OperationsEnum $operation = OperationsEnum::SELECT): SqlSrvResult
    {
        if (!$this->config->canConnect()) {
            throw new InvalidArgumentException("The database connection is not established. Please check your configuration and ensure that the connection is valid.");
        }
        if (!$operation->canHaveResultset()) {
            throw new InvalidArgumentException("The operation type '{$operation->name}' is not valid for a query that returns a result set. Use execute() for non-result set operations.");
        }
        $this->stmt = sqlsrv_prepare($this->connection, $sql, $bindings);
        sqlsrv_execute($this->stmt);
        return SqlSrvResult::fromStatement($this->stmt, $this->dataModel);
    }

    public function execute(string $sql, array $bindings = [], OperationsEnum $operation = OperationsEnum::EXECUTE): bool
    {
        if (!$this->config->canConnect()) {
            throw new InvalidArgumentException("The database connection is not established. Please check your configuration and ensure that the connection is valid.");
        }
        if ($operation->canHaveResultset()) {
            trigger_error("Executing a query that can return a result set using execute() is not recommended. Use query() instead.", E_USER_WARNING);
        }
        if (!$this->autoCommit) {
            $this->beginTransaction();
        }
        $this->stmt = sqlsrv_prepare($this->connection, $sql, $bindings);
        $exec = sqlsrv_execute($this->stmt);
        if ($operation === OperationsEnum::INSERT) {
            $this->registerLastInsertId();
        }
        return $exec;
    }

    private function registerLastInsertId(): void
    {
        $query = "SELECT SCOPE_IDENTITY() AS last_insert_id";
        $stmt = sqlsrv_query($this->connection, $query);
        if ($stmt === false) {
            throw new RuntimeException("Failed to retrieve last insert ID: " . print_r(sqlsrv_errors(), true));
        }
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $this->lastInsertId = $row['last_insert_id'] ?? null;
    }

    public function lastInsertId(): mixed
    {
        return $this->lastInsertId ?? throw new RuntimeException("Last insert ID is not available. Ensure that an INSERT operation was performed before calling this method.");
    }

    public function beginTransaction(): void
    {
        if (!sqlsrv_begin_transaction($this->connection)) {
            throw new RuntimeException("Failed to begin transaction: " . print_r(sqlsrv_errors(), true));
        }
    }

    public function commit(): void
    {
        if (!sqlsrv_commit($this->connection)) {
            throw new RuntimeException("Failed to commit transaction: " . print_r(sqlsrv_errors(), true));
        }
    }

    public function rollback(): void
    {
        if (!sqlsrv_rollback($this->connection)) {
            throw new RuntimeException("Failed to rollback transaction: " . print_r(sqlsrv_errors(), true));
        }
    }

    public function affectedRows(): int
    {
        $rows = sqlsrv_rows_affected($this->stmt);
        if ($rows === false) {
            throw new RuntimeException("Failed to retrieve affected rows: " . print_r(sqlsrv_errors(), true));
        }
        return $rows;
    }
}