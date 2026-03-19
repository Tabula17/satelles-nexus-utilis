<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request;

use Tabula17\Satelles\Nexus\Utilis\Server\HookableServer;

interface RequestHandlerInterface
{
    public function handle(int $fd, HookableServer $server): void;
}