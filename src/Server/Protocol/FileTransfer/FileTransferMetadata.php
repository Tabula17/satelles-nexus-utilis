<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class FileTransferMetadata extends AbstractDescriptor
{
    public function __construct(
        public readonly array   $conversionParams = [],
        public readonly array   $callbackData = [],
        public readonly ?string $targetFormat = null,
        public readonly ?string $correlationId = null,
        public readonly array   $extra = []
    )
    {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'conversion_params' => $this->conversionParams,
            'callback_data' => $this->callbackData,
            'target_format' => $this->targetFormat,
            'correlation_id' => $this->correlationId ?? uniqid('corr_', true),
            'extra' => $this->extra,
        ];
    }

    public static function fromArray(array $config): static
    {
        return new self(
            conversionParams: $config['conversion_params'] ?? [],
            callbackData: $config['callback_data'] ?? [],
            targetFormat: $config['target_format'] ?? null,
            correlationId: $config['correlation_id'] ?? null,
            extra: $config['extra'] ?? []
        );
    }

}