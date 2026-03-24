<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request;

use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;

interface RequestHandlerInterface
{
    public function handle(int $fd, HamumServerInterface $server, ProtocolManagerInterface $protocolManager): Status;
}