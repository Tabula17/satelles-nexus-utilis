<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

use IteratorAggregate;
use Tabula17\Satelles\Utilis\Collection\DataModelCollection;
use Tabula17\Satelles\Utilis\Data\ModelDescriptor;

interface ResultInterface extends IteratorAggregate
{
    public function getIterator(): \Generator;
    public function fetchAll(): array;
    public function fetchOne(): ?array;
    public function fetchColumn(int $columnIndex = 0): array;
    public function rowCount(): int;

    public static function fromStatement(mixed $statement, DataModelCollection $model): static;
}