<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Rpc;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class MethodsCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return MethodDescriptor::class;
    }
    public function getPublicData(): array
    {
        return $this->map(fn(MethodDescriptor $method) => $method->getPublicData());
    }
}