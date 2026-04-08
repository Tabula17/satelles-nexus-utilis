<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class MethodsSetCollection extends TypedCollection
{
    protected static function getType(): string
    {
        return MethodsCollection::class;
    }
}