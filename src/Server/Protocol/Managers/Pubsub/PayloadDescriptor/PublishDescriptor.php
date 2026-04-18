<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Managers\Pubsub\PayloadDescriptor;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class PublishDescriptor extends AbstractDescriptor
{
    public string $topic;
    public array|string $message;
}