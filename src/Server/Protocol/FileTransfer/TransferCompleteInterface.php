<?php

namespace Tabula17\Satelles\Utilis\Server\Protocol\FileTransfer;

interface TransferCompleteInterface
{
    public function __invoke(
        string                $transferId,
        string                $finalPath, bool $success,
        ?FileTransferMetadata $requestMetadata = null,
        ?FileTransferMetadata $responseMetadata = null): bool;
}