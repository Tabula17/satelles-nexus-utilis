<?php

namespace Tabula17\Satelles\Nexus\Utilis\Process;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class TaskSubscriberDescriptor extends AbstractDescriptor
{
    protected(set) string $taskId;
    protected(set) string $action;
    protected(set) string|array|int|float $payload;
    protected(set) string $channel;
    protected(set) ?string $responseChannel;
    protected(set) float $timestamp;
    protected(set) string $origin;
    public function isValid(): bool
    {
        return isset($this->taskId, $this->action, $this->payload, $this->channel);
    }

}