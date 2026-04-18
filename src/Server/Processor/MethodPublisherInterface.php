<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Processor;

use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;

interface MethodPublisherInterface
{
    public function exposeRpcMethods(?HamumServerInterface $server = null): ?MethodsCollection;
}