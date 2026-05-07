<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer;
/**
 * @deprecated Parte de FileTransferProtocol
 */
enum FileTransferActionsEnum: string
{
    case TransferInit = 'file_transfer_init';
    case TransferChunk = 'file_transfer_chunk';
    case TransferComplete = 'file_transfer_complete';
    case RequestFile = 'request_file';
    case TransferCancel = 'transfer_cancel';
    case TransferResend = 'transfer_resend';

    public static function toArray(): array
    {
        return array_combine(
            array_column(self::cases(), 'name'),
            array_column(self::cases(), 'value')
        );
    }

    public static function propertiesMap(): array
    {
        $actions = self::toArray();
        return array_combine(
            array_map('lcfirst', array_keys($actions)),
            array_values($actions)
        );
    }
}
