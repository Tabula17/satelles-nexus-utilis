<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Request;

use Tabula17\Satelles\Nexus\Utilis\Exception\RuntimeException;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Definition;

class Unsubscribe extends Subscribe
{/*
    public function __construct(string $topic, callable|string $resolver, Definition $protocol)
    {
        parent::__construct($topic, $resolver, $protocol);
        $this->payload['action'] = $this->protocol->unsubscribe;
    }*/
    public function initialize(?array &$values): void
    {
        if(!isset($values['payload']) || !self::validatePayload($values['payload'])) {
            throw new RuntimeException('Invalid payload for publish request: ' . json_encode($values['payload'] ?? null) . '. Expected format: {"topic": "string", "message": "string|array"}');
        }
        $values['action'] = $this->protocol->unsubscribe;

    }
}