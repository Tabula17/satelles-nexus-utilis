<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

class DbConnector
{
    protected mixed $connection;

    public function __construct(private readonly DbConfig $config)
    {
    }

    public function connect(bool $reconnect = false): mixed
    {
        if ($reconnect || !$this->connection) {
            $this->connection = $this->config->getDbConnector();
        }
        return $this->connection;
    }

    public function disconnect(): void
    {
        $this->connection = null;
    }

    public function close(): void
    {
        if ($this->connection) {
            if ($this->connection instanceof \PDO) {
                $this->disconnect();
                return;
            }
            if (method_exists($this->connection, 'close')) {
                $this->connection->close();
                return;
            }
            switch ($this->config->driver) {
                case DriversEnum::MYSQL:
                    $this->connection->close();
                    break;
                case DriversEnum::SQLSRV:
                    sqlsrv_close($this->connection);
                    break;
                case DriversEnum::PGSQL:
                    pg_close($this->connection);
                    break;
                case DriversEnum::SQLITE:
                    $this->connection = null;
                    break;
                case DriversEnum::ORACLE:
                    oci_close($this->connection);
                    break;
            }
        }
    }
    public function freeStatement(mixed $stmt): void
    {
        //oci
        //oci_free_statement($stmt);
        //mysqli
        //mysqli_stmt_close($stmt);
        //pdo
        // $stmt->closeCursor();
        //postgres
        //pg_free_result($stmt);
        //sqlsrv
        //sqlsrv_free_stmt($stmt);


        //$this->close();
    }
    public function beginTransaction(): void
    {
        if (!$this->connection) {
            $this->connect();
        }
        if($this->config->get('usePdo')) {
            $this->connection->beginTransaction();
        }
        //oracle en oci_execute pasar , OCI_NO_AUTO_COMMIT como 2º argumento
        //mysqli
    }
    public function commit(): void
    {
        if($this->config->get('usePdo')) {
            $this->connection->commit();
        }
        //oracle oci_commit
    }
    public function rollBack(): void
    {
        if($this->config->get('usePdo')) {
            $this->connection->rollBack();
        }
        //oracle oci_rollback
    }
}