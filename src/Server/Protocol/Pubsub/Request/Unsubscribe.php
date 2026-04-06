<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Request;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\Definition;

class Unsubscribe extends Subscribe
{
    public function __construct(string $topic, callable|string $resolver, Definition $protocol)
    {
        parent::__construct($topic, $resolver, $protocol);
        $this->payload['action'] = $this->protocol->unsubscribe;
    }
}