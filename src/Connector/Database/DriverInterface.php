<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

interface DriverInterface
{
    public function query(string $sql, array $bindings = [], OperationsEnum $operation = OperationsEnum::SELECT): ResultInterface;

    public function execute(string $sql, array $bindings = [], OperationsEnum $operation = OperationsEnum::EXECUTE): bool;

    public function lastInsertId(): mixed;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    public function affectedRows(): int;

}