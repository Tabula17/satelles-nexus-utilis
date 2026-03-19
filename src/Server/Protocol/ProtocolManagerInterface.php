<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol;


use Swoole\Server;
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
    public function initializeOnStart(Server $server): void;
    public function initializeOnWorkers(Server $server, int $workerId);
    public function runOnOpenConnection(Server $server, int $fd, int $reactorId): void;
    public function runOnCloseConnection(Server $server, int $fd, int $reactorId): void;
    public function cleanUpResources(Server $server, int $fd = 0): void;
    public function registerProtocolHandlers(Server $server): void;

}