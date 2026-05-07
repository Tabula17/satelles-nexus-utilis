<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer\TransferCompleteInterface;

class AnonymousWrapper implements TransferCompleteInterface
{
    private array $callbacks;
    public function __construct(callable...$args)
    {
        $this->callbacks = $args;
    }

    public function __invoke(string $transferId, string $finalPath, bool $success): bool
    {
        foreach ($this->callbacks as $callback) {
            $callback($transferId, $finalPath, $success);
        }
        return true;
    }
}