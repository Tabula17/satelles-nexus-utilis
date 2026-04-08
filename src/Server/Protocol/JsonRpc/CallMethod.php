<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\JsonRpc;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data\MethodDescriptor;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Payload;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Base;

class CallMethod extends Payload
{
//MethodDescriptor
    public MethodDescriptor $payload {
        get {
            return $this->payload;
        }
        set(array|MethodDescriptor $payload) {
            if (is_array($payload)) {
                $payload = new MethodDescriptor($payload);
            }
            $this->payload = $payload;

        }
    }
    public Definition $protocol {
        get {
            return $this->protocol;
        }
    }
    protected null|string $idProperty {
        get {
            return 'requestId';
        }
    }
    protected(set) string $requestId;

    public function initialize(?array &$values): void
    {
        // TODO: Implement initialize() method.
    }

    public function datasetInResponse(): bool
    {
        // TODO: Implement datasetInResponse() method.
    }

    public function handle(...$args): static
    {
        // TODO: Implement handle() method.
    }

    public function getResponse(...$args): Base
    {
        // TODO: Implement getResponse() method.
    }

    public function getResult(...$args): mixed
    {
        // TODO: Implement getResult() method.
    }

    public static function validatePayload(array $data): bool
    {
        // TODO: Implement validatePayload() method.
    }
}