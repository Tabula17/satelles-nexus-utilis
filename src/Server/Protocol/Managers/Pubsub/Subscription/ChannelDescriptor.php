<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Managers\Pubsub\Subscription;

use Swoole\Table;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class ChannelDescriptor extends AbstractDescriptor
{
    protected(set) string $name;
    protected(set) bool $autoSubscribe = false;
    protected(set) int $subscriberCount = 0;
    protected(set) int $createdAt;
    protected(set) int $lastMessageAt = 0;
    protected(set) int $lastMessageFd = 0;
    protected(set) bool $requiresAuth = false;
    protected(set) string $requiresRole {
        set(?string $value) {
            if (!empty($value)) {
                $this->requiresAuth = true;
            }
            $this->requiresRole = $value;
        }
    }
    protected(set) bool $persistsOnEmpty = false;
    protected(set) array $permissions = [];

    public function __construct(string $name, array $properties = [])
    {
        $this->name = $name;
        $this->createdAt = time();
        if (isset($properties['name'])) {
            unset($properties['name']);
        }
        parent::__construct($properties);
    }

    public static function asTable(int $size = 1024): Table
    {
        $channel = new Table($size);
        $channel->column('name', Table::TYPE_STRING, 255);
        $channel->column('autoSubscribe', Table::TYPE_INT, 1);
        $channel->column('subscriberCount', Table::TYPE_INT);
        $channel->column('createdAt', Table::TYPE_INT);
        $channel->column('lastMessageAt', Table::TYPE_INT);
        $channel->column('lastMessageFd', Table::TYPE_INT);
        $channel->column('requiresAuth', Table::TYPE_INT, 1);
        $channel->column('requiresRole', Table::TYPE_STRING, 255);
        $channel->column('persistsOnEmpty', Table::TYPE_INT, 1);
        $channel->create();
        return $channel;
    }

    public static function fromTable(array $row): self
    {
        return new self($row['name'], $row);
    }

}