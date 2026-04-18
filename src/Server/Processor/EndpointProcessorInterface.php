<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Processor;

use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;

interface EndpointProcessorInterface
{
    public function initializeOnStart(HamumServerInterface $server): void;
    public function initializeOnWorkers(HamumServerInterface $server, int $workerId): void;
    public function cleanUpOnWorkerStop(HamumServerInterface $server, int $workerId): void;
    public function cleanUpResources(HamumServerInterface $server, int $fd = 0): void;

}