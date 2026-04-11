<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Rpc;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class RpcProcessorCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return RpcProcessorInterface::class;
    }
}