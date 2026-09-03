<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

use IteratorAggregate;

interface ResultInterface extends IteratorAggregate
{
    public function fetchAll(): array;
}