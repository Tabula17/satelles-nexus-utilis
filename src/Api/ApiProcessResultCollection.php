<?php

namespace Tabula17\Satelles\Utilis\Api;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class ApiProcessResultCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return ApiProcessResult::class;
    }
}