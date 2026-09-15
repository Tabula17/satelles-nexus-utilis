<?php

namespace Tabula17\Satelles\Utilis\Server\Processor;

use Tabula17\Satelles\Utilis\Server\Hamum\HamumServerInterface;

interface MethodPublisherInterface
{
    public function exposeRpcMethods(?HamumServerInterface $server = null): ?MethodsCollection;
}