<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database\Result;

use PDOStatement;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\ResultInterface;
use Tabula17\Satelles\Utilis\Collection\DataModelCollection;

 class PdoResult implements ResultInterface
{
    private int $count;
    public function __construct(private readonly PdoStatement $statement, private readonly DataModelCollection $model) {
        $this->count = 0;
    }
    /**
     * @inheritDoc
     */
    public function getIterator(): \Generator
    {
        /*
            $pdo->setAttribute(PDO::ATTR_CURSOR, PDO::CURSOR_SCROLL); // O usas:
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
         */
        $this->statement->setFetchMode(\PDO::FETCH_ASSOC);
        $hydrator = $this->compileHydrator();
        while ($row = $this->statement->fetch(\PDO::FETCH_ASSOC)) {
            $row = $hydrator($row);
            $this->count++;
            yield $row;
        }
    }
    public function fetchAll(): array
    {
        $this->statement->setFetchMode(\PDO::FETCH_ASSOC);
        $hydrator = $this->compileHydrator();
        $rows = [];
        while ($row = $this->statement->fetch(\PDO::FETCH_ASSOC)) {
            $rows[] = $hydrator($row);
        }
        $this->count = count($rows);
        return $rows;
    }
    public function fetchOne(): ?array
    {
        $this->statement->setFetchMode(\PDO::FETCH_ASSOC);
        $row = $this->statement->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $hydrator = $this->compileHydrator();
        return $hydrator($row);
    }
    public function fetchColumn(int $columnIndex = 0): array
    {
        $res = [];
        while ($row = $this->statement->fetch(\PDO::FETCH_NUM)) {
            $res[] = $row[$columnIndex] ?? null;
        }
        return $res;
    }
    public function rowCount(): int
    {
        return $this->count;
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
    public function __destruct()
    {
        $this->statement->closeCursor();
    }
}