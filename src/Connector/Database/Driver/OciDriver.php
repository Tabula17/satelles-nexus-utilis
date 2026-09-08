<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database\Driver;

use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DbConfig;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DriverInterface;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DriversEnum;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\OperationsEnum;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\Result\OciResult;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\ResultInterface;
use Tabula17\Satelles\Nexus\Utilis\Exception\InvalidArgumentException;
use Tabula17\Satelles\Nexus\Utilis\Exception\RuntimeException;
use Tabula17\Satelles\Utilis\Collection\DataModelCollection;

class OciDriver implements DriverInterface
{
    private mixed $connection;
    private mixed $lastInsertId;
    /**
     * @var false|resource
     */
    private mixed $stmt;

    public function __construct(
        private DbConfig                     $config,
        private readonly DataModelCollection $dataModel = new DataModelCollection(),
        private readonly bool                $unbuffered = false,
        private bool                         $autoCommit = true

    )
    {
        if($this->config->driver !== DriversEnum::ORACLE) {
            throw new \InvalidArgumentException("Invalid driver type for OciDriver. Expected 'oci', got '{$this->config->driver}'.");
        }
        $this->config->set('usePdo', false);
        $this->connection = $this->config->getConnector();

    }

    public function query(string $sql, array $bindings = [], OperationsEnum $operation = OperationsEnum::SELECT): ResultInterface
    {
        if (!$this->config->canConnect()) {
            throw new InvalidArgumentException("The database connection is not established. Please check your configuration and ensure that the connection is valid.");
        }
        if (!$operation->canHaveResultset()) {
            throw new InvalidArgumentException("The operation type '{$operation->name}' is not valid for a query that returns a result set. Use execute() for non-result set operations.");
        }
        $this->stmt = oci_parse($this->connection, $sql);

        if ($this->unbuffered) {
            oci_set_prefetch($this->stmt, 500);
        }


        foreach ($bindings as $param => $val) {
            oci_bind_by_name($this->stmt, $param, $bindings[$param]);
        }

        oci_execute($this->stmt, $this->autoCommit ? OCI_COMMIT_ON_SUCCESS : OCI_NO_AUTO_COMMIT);
        return new OciResult($this->stmt, $this->dataModel);

    }

    /**
     * @throws RuntimeException
     */
    public function execute(string $sql, array $bindings = [], OperationsEnum $operation = OperationsEnum::EXECUTE): bool
    {
        if (!$this->config->canConnect()) {
            throw new InvalidArgumentException("The database connection is not established. Please check your configuration and ensure that the connection is valid.");
        }
        if ($operation->canHaveResultset()) {
            trigger_error("Operation {$operation->name} can have a result set. Use query() instead of execute().", E_USER_WARNING);
            $result = $this->query($sql, $bindings, $operation);
            return $result->rowCount() > 0;
        }
        if ($operation === OperationsEnum::INSERT) {
            $sql .= " RETURNING id INTO :lastInsertId";
            $bindings[':lastInsertId'] = &$this->lastInsertId;
        }
        $this->stmt = oci_parse($this->connection, $sql);
        if (!$this->stmt) {
            $e = oci_error($this->connection);
            throw new RuntimeException("Failed to prepare statement: " . $e['message']);
        }

        foreach ($bindings as $param => $val) {
            oci_bind_by_name($this->stmt, $param, $bindings[$param]);
        }
        $exec = oci_execute($this->stmt, $this->autoCommit ? OCI_COMMIT_ON_SUCCESS : OCI_NO_AUTO_COMMIT);
        if (!$exec) {
            $e = oci_error($this->stmt);
            throw new RuntimeException("Failed to execute statement: " . $e['message']);
        }
        if ($operation === OperationsEnum::UPDATE || $operation === OperationsEnum::DELETE || $operation === OperationsEnum::INSERT) {
            return $this->affectedRows() > 0;
        }
        return $exec;
    }

    public function affectedRows(): int
    {
        return oci_num_rows($this->stmt);
    }

    public function lastInsertId(): mixed
    {
        return $this->lastInsertId;
    }

    public function beginTransaction(): void
    {
        $this->autoCommit = false;
    }

    public function commit(): void
    {
        oci_cancel($this->connection);
    }

    public function rollback(): void
    {
        oci_rollback($this->connection);
    }
}