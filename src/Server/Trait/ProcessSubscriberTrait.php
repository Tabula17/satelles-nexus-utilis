<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Trait;

use Tabula17\Satelles\Nexus\Utilis\Process\AbstractSubscriberProcess;

trait ProcessSubscriberTrait
{
    final public const bool PROCESS_SUBSCRIBER_ENABLED = true;
    private array $processSubscribers = [];

    public function addProcessSubscriber(AbstractSubscriberProcess $subscriber): void
    {
        $subscriber->init();
        $this->processSubscribers[$subscriber->origin] = $subscriber;
    }

}