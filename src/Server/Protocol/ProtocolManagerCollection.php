<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class ProtocolManagerCollection extends TypedCollection
{
    /**
     * @return string
     */
    protected static function getType(): string
    {
       return ProtocolManagerInterface::class;
    }
}