<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Managers\Pubsub\Request;

use Tabula17\Satelles\Nexus\Utilis\Exception\RuntimeException;

class Unsubscribe extends Subscribe
{/*
    public function __construct(string $topic, callable|string $resolver, Definition $protocol)
    {
        parent::__construct($topic, $resolver, $protocol);
        $this->payload['action'] = $this->protocol->unsubscribe;
    }*/
    public function initialize(?array &$values): void
    {
        if(!isset($values['payload']) || !static::validatePayload($values['payload'])) {
            throw new RuntimeException('Invalid payload for publish request: ' . json_encode($values['payload'] ?? null) . '. Expected format: {"topic": "string", "message": "string|array"}');
        }
        $values['action'] = $this->protocol->unsubscribe;

    }
}