<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

interface DriverInterface
{
    public function query(string $sql, array $bindings = []): ResultInterface;
    public function execute(string $sql, array $bindings = []): bool;
    public function lastInsertId(): int;
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;

}