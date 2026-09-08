<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database\Driver;

use PDO;
use PDOStatement;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DbConfig;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\DriverInterface;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\OperationsEnum;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\Result\PdoResult;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\ResultInterface;
use Tabula17\Satelles\Nexus\Utilis\Exception\InvalidArgumentException;
use Tabula17\Satelles\Utilis\Collection\DataModelCollection;

class PdoDriver implements DriverInterface
{
    private PDO $pdo;
    private ?PDOStatement $statement = null;

    public function __construct(
        private readonly DbConfig            $config,
        private readonly DataModelCollection $dataModel = new DataModelCollection(),
        private readonly bool                $unbuffered = false
    )
    {
        $this->config->set('usePdo', true);
        $this->pdo = $this->config->getConnector();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
//        $this->pdo->setAttribute(PDO::ATTR_PERSISTENT, true);
   //     $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, $this->autoCommit);
        if($this->unbuffered) {
            $this->configureUnbuffered();
        }
    }

    private function configureUnbuffered(): void
    {
        switch ($this->config->get('driver')) {
            case 'mysql':
                $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                break;
            case 'pgsql':
                $this->pdo->setAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES, true);
                break;
            case 'sqlsrv':
                $this->pdo->setAttribute(PDO::SQLSRV_ATTR_QUERY_TIMEOUT, 0);
                break;
            case 'oci8':
                $this->pdo->setAttribute(PDO::ATTR_CURSOR, PDO::CURSOR_SCROLL);
                $this->pdo->setAttribute(PDO::ATTR_PREFETCH, 500);
                break;
        }
    }

    public function query(string $sql, array $bindings = [], OperationsEnum $operation = OperationsEnum::SELECT): ResultInterface
    {
        if (!$this->config->canConnect()) {
            throw new InvalidArgumentException("The database connection is not established. Please check your configuration and ensure that the connection is valid.");
        }
        if (!$operation->canHaveResultset()) {
            throw new InvalidArgumentException("The operation type '{$operation->name}' is not valid for a query that returns a result set. Use execute() for non-result set operations.");
        }
        $this->statement = $this->pdo->prepare($sql);
        $this->statement->execute($bindings);
        return new PdoResult($this->statement, $this->dataModel);
    }

    public function execute(string $sql, array $bindings = [], OperationsEnum $operation = OperationsEnum::EXECUTE): bool
    {
        if (!$this->config->canConnect()) {
            throw new InvalidArgumentException("The database connection is not established. Please check your configuration and ensure that the connection is valid.");
        }
        if($operation->canHaveResultset()) {
            trigger_error("Operation {$operation->name} can have a result set. Use query() instead of execute().", E_USER_WARNING);
            $result = $this->query($sql, $bindings, $operation);
            return $result->rowCount() > 0;
        }
        $this->statement = $this->pdo->prepare($sql);
        $this->statement->execute($bindings);
        return true;
    }

    public function lastInsertId(): mixed
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {

        $this->pdo->commit();
    }


    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    public function affectedRows(): int
    {
        return $this->statement->rowCount();
    }
}