<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Managers\Pubsub\Subscription;

use Swoole\Table;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class SubscriptionDescriptor extends AbstractDescriptor
{

    protected(set) string $id;
    protected(set) string $channel;
    protected(set) int $subscriberFd;

    public function __construct(string $id, string $channel, int $subscriberFd)
    {
        $values = compact('id', 'channel', 'subscriberFd');
        parent::__construct($values);
    }

    public static function asTable(int $size = 1024): Table
    {
        $subscriptions = new Table($size);
        $subscriptions->column('id', Table::TYPE_STRING, 255);
        $subscriptions->column('channel', Table::TYPE_STRING, 255);
        $subscriptions->column('subscriberFd', Table::TYPE_INT);
        $subscriptions->create();
        return $subscriptions;
    }
}