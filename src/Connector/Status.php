<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector;

enum Status:string
{
    case CONNECTING = 'connecting';
    case CONNECTED = 'connected';
    case DISCONNECTED = 'disconnected';
    case FAILED = 'failed';
    case UNREACHABLE = 'unreachable';
    case ACTIVE = 'active';
    case READY = 'ready';
    case FULL = 'full';
    case EMPTY = 'empty';

    public function isActive(): bool
    {
        return $this === self::ACTIVE || $this === self::CONNECTED;
    }
    public function hasFailure(): bool
    {
        return $this === self::FAILED || $this === self::UNREACHABLE;
    }
    public function isConnecting(): bool
    {
        return $this === self::CONNECTING;
    }
    public function isFull(): bool
    {
        return $this === self::FULL;
    }
    public function isEmpty(): bool
    {
        return $this === self::EMPTY;
    }
}
