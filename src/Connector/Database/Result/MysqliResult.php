<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database\Result;

use mysqli_stmt;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\ResultInterface;
use Tabula17\Satelles\Utilis\Collection\DataModelCollection;

class MysqliResult implements ResultInterface
{

    public function __construct(
        private readonly mysqli_stmt         $statement,
        private readonly DataModelCollection $model
    )
    {
    }

    public function getIterator(): \Generator
    {
        $hydrator = $this->compileHydrator();
        $row = $this->getEmptyRow();
        $references = [];
        foreach ($row as $key => &$value) {
            $references[] = &$value;
        }
        unset($value);
        $this->statement->bind_result(...$references);
        while ($this->statement->fetch()) {
            yield $hydrator($row);
        }
        $this->statement->free_result();
    }

    private function getEmptyRow(): array
    {
        $result = $this->statement->result_metadata();
        if ($result === false) {
            return [];
        }

        while ($field = $result->fetch_field()) {
            $params[$field->name] = null;
        }
        $result->free();
        return $params ?? [];
    }

    public function fetchAll(): array
    {
        $hydrator = $this->compileHydrator();
        $rows = [];
        $result = $this->statement->get_result();
        $all_rows = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($all_rows as $row) {
            $rows[] = $hydrator($row);
        }
        $result->free();
        return $rows;
    }

    public function fetchOne(): ?array
    {
        $hydrator = $this->compileHydrator();
        $result =  $this->statement->get_result();
        $row = $result->fetch_assoc();
        if ($row === false) {
            return null;
        }
        $result->free();
        return $hydrator($row);
    }

    public function fetchColumn(int $columnIndex = 0): array
    {
        $res = [];
        $result =  $this->statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $res[] = $row[$columnIndex] ?? null;
        }
        $result->free();
        return $res;
    }

    public function rowCount(): int
    {
        return $this->statement->get_result()->num_rows;
    }

    public static function fromStatement(mixed $statement, DataModelCollection $model): static
    {
        return new static($statement, $model);
    }

    public function compileHydrator(): callable
    {
        return function (array $row) {
            $data = [];
            foreach ($row as $key => $value) {
                $data[$key] = $this->model->get($key)?->hydrate($value) ?? $value;
            }
            return $data;
        };
    }

    public function __destruct()
    {
        $this->statement->close();
    }
}