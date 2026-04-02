<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol;

enum ServiceProtocol: string
{
    case RPC = 'Custom Remote Procedure Call Protocol';
    case PUBSUB = 'Pub/Sub Pattern Protocol';
    case AUTH = 'Authentication Protocol (e.g., Basic, Digest, Bearer Token)';
    case OAUTH = 'OAuth 2.0 Protocol';
    case JWT = 'JSON Web Token (JWT)';
    case JSONRPC = 'JSON-RPC 2.0 Protocol';
    case EXTDIRECT = 'ExtJS Direct Protocol';
    case WAMP = 'Web Application Messaging Protocol';
    case WAMP2 = 'Web Application Messaging Protocol 2.0';
    case REQRES = 'Request-Response Pattern';
    case GENERIC = 'Generic/other Protocol: unspecified or custom';
    case UNKNOWN = 'Unknown Protocol';

    public function isRecognized(): bool
    {
        return $this !== self::UNKNOWN && $this !== self::GENERIC;
    }

    public function isAuth(): bool
    {
        return $this === self::AUTH || $this === self::OAUTH || $this === self::JWT;
    }

    public function isPubSub(): bool
    {
        return $this === self::PUBSUB;
    }

    public function isRpc(): bool
    {
        return $this === self::RPC || $this === self::JSONRPC || $this === self::EXTDIRECT;
    }

    public function isGeneric(): bool
    {
        return $this === self::GENERIC;
    }

    public function supportWs(): bool
    {
        return match ($this) {
            self::RPC, self::JSONRPC, self::WAMP, self::WAMP2, self::REQRES => true,
            default => false,
        };
    }

    public function supportHttp(): bool
    {
        return match ($this) {
            self::RPC, self::JSONRPC, self::EXTDIRECT, self::AUTH, self::OAUTH, self::JWT => true,
            default => false,
        };
    }

    public static function fromString(string $protocol): self
    {
        $protocol = strtoupper($protocol);
        return match ($protocol) {
            'RPC' => self::RPC,
            'PUBSUB' => self::PUBSUB,
            'AUTH' => self::AUTH,
            'OAUTH' => self::OAUTH,
            'JWT' => self::JWT,
            'JSONRPC' => self::JSONRPC,
            'EXTDIRECT' => self::EXTDIRECT,
            'WAMP' => self::WAMP,
            'WAMP2' => self::WAMP2,
            'REQRES' => self::REQRES,
            'OTHER', 'GENERIC' => self::GENERIC,
            default => self::UNKNOWN,
        };
    }

    public function shortName(): string
    {
        return match ($this) {
            self::RPC => 'rpc',
            self::PUBSUB => 'pubsub',
            self::AUTH => 'auth',
            self::JWT => 'jwt',
            self::OAUTH => 'oauth',
            self::JSONRPC => 'jsonrpc',
            self::EXTDIRECT => 'extjs',
            self::WAMP => 'wamp',
            self::WAMP2 => 'wamp2',
            self::REQRES => 'reqres',
            self::GENERIC => 'generic',
            default => 'unknown',
        };
    }
}
