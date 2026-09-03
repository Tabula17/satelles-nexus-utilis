<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database\Result;

use PDOStatement;
use Tabula17\Satelles\Nexus\Utilis\Connector\Database\ResultInterface;
use Traversable;

class Pdo implements ResultInterface
{

    public function __construct(private PdoStatement $statement) {
    }
    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {

    }

    public function fetchAll(): array
    {
        // TODO: Implement fetchAll() method.
    }
}