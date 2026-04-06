<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Request;

use Tabula17\Satelles\Nexus\Utilis\Exception\RuntimeException;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Definition;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Action;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Payload;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\PayloadDescriptor\PublishDescriptor;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Base;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;

class Publish extends Payload
{

    protected(set) Definition $protocol {
        get {
            return $this->protocol;
        }
        set(array|Definition $protocol) => $this->protocol = $protocol instanceof Definition ? $protocol : new Definition($protocol);
    }
    protected(set) PublishDescriptor $payload
        {
            get {
                return $this->payload;
            }
            set(array|PublishDescriptor $payload) {
                if (is_array($payload)) {
                    $payload = new PublishDescriptor($payload);
                }
                $this->payload = $payload;
            }
        }
/*
    public function __construct(string $topic, array|string $message, callable|string $resolver, Definition $protocol)
    {
        parent::__construct(resolver: $resolver, protocol: $protocol);
        $values = [
            'action' => $protocol->publish,
            'payload' => [
                'topic' => $topic,
                'message' => $this->formatMessage($message)
            ]
        ];
        $this->loadProperties($values);
    }*/
    public function initialize(?array &$values): void
    {
        if(!isset($values['payload']) || !static::validatePayload($values['payload'])) {
            throw new RuntimeException('Invalid payload for publish request: ' . json_encode($values['payload'] ?? null) . '. Expected format: {"topic": "string", "message": "string|array"}');
        }
        $values['payload']['message'] = $this->formatMessage($values['payload']['message']);
        $values['action'] = $this->protocol->publish;

    }

    protected function formatMessage(array|string $message): array
    {
        if (is_string($message)) {
            $message = json_validate($message) ? json_decode($message, true) : ['message' => $message];
        }
        return $message;
    }

    public function datasetInResponse(): bool
    {
        // When publish a request we don't expect a result, only a confirmation of success or failure,
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
        $keys = ['topic' => true, 'message' => true];
        // Check if the payload has the required fields and that they are of the correct type
        var_dump( !array_diff_key($data, $keys), !array_diff_key($keys, $data), $data);
        return
            !array_diff_key($data, $keys) &&
            !array_diff_key($keys, $data) &&
            is_string($data['topic']) && !empty($data['topic']) &&
            (is_array($data['message']) || is_string($data['message']));

    }

    public function getResponse(...$args): Base
    {
        $responseClass = $this->protocol->getResponseType($this->action);
        if (!$responseClass) {
            throw new \RuntimeException("No response class found for action {$this->action}");
        }
        //array_unshift($args, $this->toArray());
        return new $responseClass(
            $this->status, ...$args
        );
    }

    public function getResult(...$args): mixed
    {
        return $this->status;
    }
}