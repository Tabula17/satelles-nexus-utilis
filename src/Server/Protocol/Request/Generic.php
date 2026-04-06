<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request;

use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\RequestHandlerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class Generic extends AbstractDescriptor implements RequestHandlerInterface
{

    public function handle(array $data, int $fd, HamumServerInterface $server, ?ProtocolManagerInterface $protocolManager): Status
    {
        echo 'Generic request handler' . PHP_EOL;
        echo var_export($data, true) . PHP_EOL;
        return Status::unknown;
    }

    public function __invoke(...$args): Status
    {
        return $this->handle(...$args);
    }
}