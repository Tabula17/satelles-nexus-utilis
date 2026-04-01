<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Hamum;

enum HamumTypes
{
    case WEBSOCKET;
    case HTTP;
    case TCP;
    case UDP;

    public function isWebsocket(): bool
    {
        return $this === self::WEBSOCKET;
    }

    public function isHttp(): bool
    {
        return $this === self::HTTP;
    }

    public function isTcp(): bool
    {
        return $this === self::TCP;
    }

    public function isUdp(): bool
    {
        return $this === self::UDP;
    }
}
