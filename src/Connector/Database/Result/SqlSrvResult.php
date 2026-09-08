<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database\Result;

use Tabula17\Satelles\Nexus\Utilis\Connector\Database\ResultInterface;
use Tabula17\Satelles\Utilis\Collection\DataModelCollection;

class SqlSrvResult implements ResultInterface
{

    public function __construct(private readonly mixed $statement, private readonly DataModelCollection $model) {
    }
    public function getIterator(): \Generator
    {
        $hydrator = $this->compileHydrator();
        while ($row = sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_ASSOC)) {
            yield $hydrator($row);
        }
        sqlsrv_free_stmt($this->statement);
    }

    public function fetchAll(): array
    {
        $res = [];
        $hydrator = $this->compileHydrator();
        while ($row = sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_ASSOC)) {
            $res[] =  $hydrator($row);
        }
        sqlsrv_free_stmt($this->statement);
        return $res;
    }

    public function fetchOne(): ?array
    {
        $row = sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $hydrator = $this->compileHydrator();
        return $hydrator($row);
    }

    public function fetchColumn(int $columnIndex = 0): array
    {
        $res = [];
        while ($row = sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_NUMERIC)) {
            $res[] = $row[$columnIndex] ?? null;
        }
        sqlsrv_free_stmt($this->statement);
        return $res;
    }

    public function rowCount(): int
    {
        return sqlsrv_num_rows($this->statement);
    }

    public static function fromStatement(mixed $statement, DataModelCollection $model): static
    {
        return new static($statement, $model);
    }
    private function compileHydrator(): callable
    {
        return function (array $row) {
            foreach ($row as $key => $value) {
                $row[$key] = $this->model->get($key)?->hydrate($value) ?? $value;
            }
            return $row;
        };
    }
}