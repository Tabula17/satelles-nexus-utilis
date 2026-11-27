<?php

namespace Tabula17\Satelles\Utilis\Collection;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;
use Tabula17\Satelles\Utilis\Config\TCPClientConfig;

class TCPClientCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return TCPClientConfig::class;
    }
}