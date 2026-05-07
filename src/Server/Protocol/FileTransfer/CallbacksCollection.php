<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class CallbacksCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return TransferCompleteInterface::class;
    }
}