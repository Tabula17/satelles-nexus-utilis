<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class Stats extends AbstractDescriptor
{
    protected(set) int $worker_id;
    protected(set) float $execution_time;
    protected(set) int $timestamp;
    protected(set) string $server_time;
    protected(set) ?int $client_fd;
    protected(set) ?string $origin_server;
    protected(set) ?string $publisher;

    public function __construct(
        int     $worker_id,
        ?float  $execution_time = null,
        ?int    $timestamp = null,
        ?string $server_time = null,
        ?int    $client_fd = null,
        ?string $origin_server = null,
        ?string $publisher = null)
    {
        parent::__construct(
            [
                'worker_id' => $worker_id,
                'execution_time' => $execution_time ?? round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3),
                'timestamp' => $timestamp ?? time(),
                'server_time' => $server_time ?? date('Y-m-d H:i:s'),
                'client_fd' => $client_fd,
                'origin_server' => $origin_server,
                'publisher' => $publisher
            ]
        );
    }

    public function toArray(): array
    {
        $this->execution_time = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3);
        return parent::toArray();
    }
}