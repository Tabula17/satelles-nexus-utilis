<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol;


use Swoole\Server;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Action;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Type;
use Swoole\Http\Request;

interface ProtocolManagerInterface
{
    public Action $protocol {
        get;
    }
    public Type $responses{
        get;
    }
    public function initializeOnStart(HamumServerInterface $server): void;
    public function initializeOnWorkers(HamumServerInterface $server, int $workerId);
    public function runOnOpenConnection(...$args): void; // HamumServerInterface $server, int $fd, int $reactorId but WS -> HamumServerInterface $server, Request $request
    public function runOnCloseConnection(HamumServerInterface $server, int $fd, int $reactorId): void;
    public function cleanUpResources(HamumServerInterface $server, int $fd = 0): void;
    public function registerProtocolHandlers(HamumServerInterface $server): void;

}