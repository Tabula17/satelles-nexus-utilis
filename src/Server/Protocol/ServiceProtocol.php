<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol;

enum ServiceProtocol: string
{
    /**
     * Represents the Custom Remote Procedure Call Protocol.
     * @see https://en.wikipedia.org/wiki/Remote_procedure_call
     */
    case RPC = 'Custom Remote Procedure Call Protocol';
    /**
     * Represents the Pub/Sub Pattern Protocol.
     * 1. Publisher sends a message to a topic.
     * 2. Subscriber listens for messages on the topic.
     *
     * @see https://en.wikipedia.org/wiki/Publish%E2%80%93subscribe_pattern
     *
     * --> Subribe to topic/channel. Protocol define name convention for topic/channel
     * { "topic": "topic/channel"}
     * <-- Publish message to topic/channel. Protocol define message format for publish message
     * { "topic": "topic/channel", "message": "Hello, world!"} // or maybe a json encoded message
     */
    case PUBSUB = 'Pub/Sub Pattern Protocol';
    /**
     * Represents the Authentication Protocol (e.g., Basic, Digest, Bearer Token).
     * @see https://en.wikipedia.org/wiki/Basic_access_authentication
     * @see https://en.wikipedia.org/wiki/Digest_access_authentication
     * @see https://en.wikipedia.org/wiki/Bearer_token
     */
    case AUTH = 'Authentication Protocol (e.g., Basic, Digest, Bearer Token)';
    /**
     * Represents the OAuth 2.0 Protocol.
     * @see https://oauth.net/2/
     */
    case OAUTH = 'OAuth 2.0 Protocol';
    /**
     * Represents the JSON Web Token (JWT) Protocol.
     * @see https://jwt.io/introduction/
     */
    case JWT = 'JSON Web Token (JWT)';
    /**
     * Represents the JSON-RPC 2.0 Protocol.
     * --> {
     *      "jsonrpc": "2.0",
     *      "method": "subtract",
     *      "params": {
     *          "minuend": 42,
     *          "subtrahend": 23
     *      },
     *      "id": 3
     *  }
     * <--
     * {
     *      "jsonrpc": "2.0",
     *      "result": 19,
     *      "id": 3
     * }
     */
    case JSONRPC = 'JSON-RPC 2.0 Protocol';
    /**
     * Represents the ExtJS Direct Protocol.
     * @see https://docs.sencha.com/extjs/7.0.0/extjs/Ext.direct.RemotingProvider.html
     * @see https://docs.sencha.com/extjs/7.0.0/extjs/Ext.direct.RemotingEvent.html
     * @see https://docs.sencha.com/extjs/7.0.0/extjs/Ext.direct.RemotingMethod.html
     * @see https://docs.sencha.com/extjs/7.0.0/extjs/Ext.direct.Transaction.html
     * @see https://docs.sencha.com/extjs/7.0.0/extjs/Ext.direct.Event.html
     * @see https://docs.sencha.com/extjs/7.0.0/extjs/Ext.direct.Provider.html
     * @see https://docs.sencha.com/extjs/7.0.0/extjs/Ext.direct.Manager.html
     *
     */
    case EXTDIRECT = 'ExtJS Direct Protocol';
    /**
     * Represents the Web Application Messaging Protocol.
     * @see https://wamp-proto.org/
     */
    case WAMP = 'Web Application Messaging Protocol';
    /**
     * Represents the Web Application Messaging Protocol 2.0.
     * @see https://wamp-proto.org/
     */
    case WAMP2 = 'Web Application Messaging Protocol 2.0';
    /**
     * Represents the Definition-Response Pattern.
     * 1. Client sends a request to a server.
     * 2. Server processes the request and sends a response.
     * 3. Client receives the response.
     * @see https://en.wikipedia.org/wiki/Request%E2%80%93response_pattern
     * @see https://en.wikipedia.org/wiki/Client%E2%80%93server_model
     */
    case REQRES = 'Definition-Response Pattern';
    /**
     * Represents the TCP File Transfer Protocol.
     */
    case TCPFILE = 'TCP File Transfer Protocol';
    /**
     * Represents a generic or custom protocol.
     */
    case GENERIC = 'Generic/other Protocol: unspecified or custom';
    /**
     * Represents an unknown or unsupported protocol.
     */
    case UNKNOWN = 'Unknown Protocol';

    /**
     * Determines if the current instance is recognized by checking that it is neither UNKNOWN nor GENERIC.
     *
     * @return bool True if the instance is recognized, false otherwise.
     */
    public function isRecognized(): bool
    {
        return $this !== self::UNKNOWN && $this !== self::GENERIC;
    }

    /**
     * Determines if the current instance is an Auth protocol.
     *
     * @return bool True if the instance represents an Auth protocol, false otherwise.
     */
    public function isAuth(): bool
    {
        return $this === self::AUTH || $this === self::OAUTH || $this === self::JWT;
    }

    /**
     * Determines if the current instance is of the PUBSUB type.
     *
     * @return bool True if the current instance is PUBSUB; otherwise, false.
     */
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
    public function hasHttpResponse(): bool
    {
        return match ($this) {
            self::RPC, self::JSONRPC, self::EXTDIRECT, self::AUTH, self::OAUTH, self::JWT, self::WAMP, self::WAMP2 => true
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
            'TCPFILE' => self::TCPFILE,
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
            self::TCPFILE => 'tcpfile',
            self::GENERIC => 'generic',
            default => 'unknown',
        };
    }
}
