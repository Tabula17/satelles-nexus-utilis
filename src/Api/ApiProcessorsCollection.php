<?php

namespace Tabula17\Satelles\Utilis\Api;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class ApiProcessorsCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return ApiProcessInterface::class;
    }
}