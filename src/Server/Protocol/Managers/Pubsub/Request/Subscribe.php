<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Managers\Pubsub\Request;

use Tabula17\Satelles\Nexus\Utilis\Exception\RuntimeException;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Managers\Pubsub\Definition;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Managers\Pubsub\PayloadDescriptor\TopicDescriptor;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Payload;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Base;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;

class Subscribe extends Payload
{

    protected null|string $idProperty {
        get {
            return 'payloadId';
        }
    }
    protected(set) string $payloadId;
    protected(set) Definition $protocol {
        get {
            return $this->protocol;
        }
        set(array|Definition $protocol) => $this->protocol = $protocol instanceof Definition ? $protocol : new Definition($protocol);
    }
    protected(set) TopicDescriptor $payload
        {
            get {
                return $this->payload;
            }
            set(array|TopicDescriptor $payload) {
                if (is_array($payload)) {
                    $payload = new TopicDescriptor($payload);
                }
                $this->payload = $payload;
            }
        }

    /*
        public function __construct(string $topic, callable|string $resolver, Definition $protocol)
        {
            parent::__construct(resolver: $resolver, protocol: $protocol);
            $values = [
                'action' => $protocol->subscribe,
                'payload' => [
                    'topic' => $topic
                ]
            ];
            $this->loadProperties($values);
        }*/

    public function initialize(?array &$values): void
    {
        if (!isset($values['payload']) || !static::validatePayload($values['payload'])) {
            throw new RuntimeException('Invalid payload for subscribe request: ' . json_encode($values['payload'] ?? null) . '. Expected format: {"topic": "string"}');
        }
        $values['action'] = $this->protocol->subscribe;

    }

    public function datasetInResponse(): bool
    {
        // When subscribe to a topic we don't expect a result, only a confirmation of success or failure,
        // so we return false to indicate that we don't expect a resultset
        return false;
    }

    public function handle(...$args): static
    {
        // Resolver can output a string, boolean, or an array with a 'status' or 'success' key.
        // We will normalize these outputs to set the status of the request accordingly.
        // If the resolver returns an array without a 'status' or 'success' key, we assume 'ok'.
        $result = ($this->resolver)($this->toArray(), ...$args);
        if (is_array($result)) {
            $status = $result['status'] ?? $result['success'] ?? 'ok';
            if (is_bool($status)) {
                $status = $status ? 'ok' : 'error';
            }
            $this->status = Status::fromString($status);
        } else {
            if (is_bool($result)) {
                $result = $result ? 'ok' : 'error';
            }
            $this->status = Status::fromString(is_string($result) ? $result : 'ok');
        }
        return $this;

    }

    public static function validatePayload(array $data): bool
    {
        $keys = ['topic' => true];
        // Check if the payload has the required fields and that they are of the correct type
        return !array_diff_key($data, $keys) && !array_diff_key($keys, $data) && is_string($data['topic']) && !empty($data['topic']);

    }

    public function getResponse(...$args): Base
    {
        $responseClass = $this->protocol->getResponseType($this->action);
        if (!$responseClass) {
            throw new \RuntimeException("No response class found for action {$this->action}");
        }
        $extra = $args[0] ?? [];
        //var_dump($this->toArray(), $this->getID());
        $extra['payloadId'] = $this->getResponseID();
        return new $responseClass(
            $this->status, $extra
        );
    }

    public function getResult(...$args): Status
    {
        return $this->status;
    }
}