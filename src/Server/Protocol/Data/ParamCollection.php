<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class ParamCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return ParamDescriptor::class;
    }
}