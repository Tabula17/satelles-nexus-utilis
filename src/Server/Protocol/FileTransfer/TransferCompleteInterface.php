<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer;

interface TransferCompleteInterface
{
    public function __invoke(string $transferId, string $finalPath, bool $success): bool;
}