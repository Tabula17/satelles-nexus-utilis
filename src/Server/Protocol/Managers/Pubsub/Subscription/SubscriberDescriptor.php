<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Managers\Pubsub\Subscription;

use Swoole\Table;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class SubscriberDescriptor extends AbstractDescriptor
{
    protected(set) int $fd;
    protected(set) int $channels;
    protected(set) int $startedAt;

    public function __construct(int $fd, array $properties = [])
    {
        $this->fd = $fd;
        $this->channels = $properties['channels'] ?? 0;
        $this->startedAt = $properties['startedAt'] ?? time();
        parent::__construct();
    }

    public static function asTable(int $size = 1024): Table
    {
        $subscriber = new Table($size);
        $subscriber->column('fd', Table::TYPE_INT);
        $subscriber->column('channels', Table::TYPE_INT);
        $subscriber->column('startedAt', Table::TYPE_INT);
        $subscriber->create();
        return $subscriber;
    }

}