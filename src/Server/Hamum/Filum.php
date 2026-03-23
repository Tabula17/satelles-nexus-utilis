<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Hamum;

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Tabula17\Satelles\Nexus\Utilis\Server\Trait\HamumTrait;
use Tabula17\Satelles\Utilis\Collection\CallableCollection;
use Tabula17\Satelles\Utilis\Config\TCPServerConfig;

abstract class Filum extends Server
{
    use HamumTrait;

    private array $requestHandlers = [];
    private array $connectHandlers = [];
    private array $receiveHandlers = [];
    /*
     *
            'message',
            'open',
            'disconnect'
     */
    private array $messageHandlers = [];
    private array $openHandlers = [];
    private array $disconnectHandlers = [];

    public function __construct(TCPServerConfig $config)
    {
        parent::__construct($config->host, $config->port, $config->mode ?? SWOOLE_BASE, $config->type ?? SWOOLE_SOCK_TCP);
        $sslEnabled = isset($config->ssl) && $config->ssl->enabled;
        $options = $sslEnabled ? array_merge($config->options, $config->ssl->toArray()) : $config->options;
        if ($sslEnabled) {
            unset($options['enabled']);
        }
        $this->set($options);
    }

    public function handleRequestEvent(string $protocolAction, $request, $response): void
    {
        $this?->logger?->debug("Handling request event with protocolAction: {$protocolAction}");
        $eventHandlers = array_merge($this->getEventActionHandlers('request', $protocolAction), $this->getEventActionHandlers('request', '*'));
        foreach ($eventHandlers as $callback) {
            $callback($request, $response);
        }
    }

    public function registerRequestHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->requestHandlers[$protocolAction]) || !($this->requestHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->requestHandlers[$protocolAction] = new CallableCollection();
        }
        $this->requestHandlers[$protocolAction]->offsetSet($protocol, $callback);
        $this->registerEventHandlers('Request');
    }

    public function getRequestHandlers(string $protocolAction): ?array
    {
        return $this->getEventActionHandlers('Request', $protocolAction);
    }

    public function hasRequestHandlers(string $protocolAction): bool
    {
        return $this->requestHandlers[$protocolAction]->offsetExists($protocolAction) && $this->requestHandlers[$protocolAction]->count() > 0;
    }

    public function removeRequestHandlers(string $protocolAction): void
    {
        $this->requestHandlers[$protocolAction]?->clear();
        unset($this->requestHandlers[$protocolAction]);
        $this->registerEventHandlers('Request');
    }

    public function registerReceiveHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->receiveHandlers[$protocolAction]) || !($this->receiveHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->receiveHandlers[$protocolAction] = new CallableCollection();
        }
        $this->receiveHandlers[$protocolAction]->offsetSet($protocol, $callback);
        $this->registerEventHandlers('receive');
    }

    public function getReceiveHandlers(string $protocolAction): ?array
    {
        return $this->receiveHandlers[$protocolAction]?->toArray();
    }

    public function hasReceiveHandlers(string $protocolAction): bool
    {
        return $this->receiveHandlers[$protocolAction]->offsetExists($protocolAction) && $this->receiveHandlers[$protocolAction]->count() > 0;
    }

    public function removeReceiveHandlers(string $protocolAction): void
    {
        $this->receiveHandlers[$protocolAction]?->clear();
        unset($this->receiveHandlers[$protocolAction]);
        $this->registerEventHandlers('receive');
    }

    public function handleReceiveEvent(Server $server, int $fd, int $reactorId, string $data): void
    {
        $this?->logger?->debug("Handling receive event for fd {$fd} with data: {$data}");
        $protocolAction = json_validate($data) ? json_decode($data, true)['action'] : '';
        if ($this->hasReceiveHandlers($protocolAction)) {
            $eventHandlers = array_merge($this->getEventActionHandlers('receive', $protocolAction), $this->getEventActionHandlers('receive', '*'));
            foreach ($eventHandlers as $callback) {
                $callback($server, $fd, $reactorId, $data);
            }
        }
    }

    public function registerConnectHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->connectHandlers[$protocolAction]) || !($this->connectHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->connectHandlers[$protocolAction] = new CallableCollection();
        }
        $this->connectHandlers[$protocolAction]->offsetSet($protocol, $callback);
        $this->registerEventHandlers('connect');
    }

    public function getConnectHandlers(string $protocolAction): ?array
    {
        return $this->connectHandlers[$protocolAction]?->toArray();
    }

    public function hasConnectHandlers(string $protocolAction): bool
    {
        return $this->connectHandlers[$protocolAction]->offsetExists($protocolAction) && $this->connectHandlers[$protocolAction]->count() > 0;
    }

    public function removeConnectHandlers(string $protocolAction): void
    {
        $this->connectHandlers[$protocolAction]?->clear();
        unset($this->connectHandlers[$protocolAction]);
        $this->registerEventHandlers('connect');
    }

    public function handleConnectEvent(Server $server, int $fd, int $reactorId): void
    {
        $this?->logger?->debug("Handling connect event for fd {$fd}");
        $eventHandlers = $this->getEventActionHandlers('connect', null);
        foreach ($eventHandlers as $callback) {
            $callback($server, $fd, $reactorId);
        }
    }

    public function registerMessageHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->messageHandlers[$protocolAction]) || !($this->messageHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->messageHandlers[$protocolAction] = new CallableCollection();
        }
        $this->messageHandlers[$protocolAction]->offsetSet($protocol, $callback);
        $this->registerEventHandlers('message');
    }

    public function getMessageHandler(string $protocolAction): ?array
    {
        return $this->messageHandlers[$protocolAction]?->toArray();
    }

    public function hasMessageHandlers(string $protocolAction): bool
    {
        return $this->messageHandlers[$protocolAction]->offsetExists($protocolAction) && $this->messageHandlers[$protocolAction]->count() > 0;
    }

    public function removeMessageHandlers(string $protocolAction): void
    {
        $this->messageHandlers[$protocolAction]?->clear();
        unset($this->messageHandlers[$protocolAction]);
        $this->registerEventHandlers('message');
    }

    public function handleMessageEvent(Server $server, Frame $frame): void
    {
        $data = json_decode($frame->data, true);
        $this?->logger?->debug("Handling message event for with data: {$data}");

        $protocolAction = json_validate($data) ? json_decode($data, true)['action'] : '';
        $eventHandlers = array_merge($this->getEventActionHandlers('message', $protocolAction) , $this->getEventActionHandlers('message', '*'));
        foreach ($eventHandlers as $callback) {
            $callback($server, $data);
        }
    }

    public function registerOpenHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->openHandlers[$protocolAction]) || !($this->openHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->openHandlers[$protocolAction] = new CallableCollection();
        }
        $this->openHandlers[$protocolAction]->offsetSet($protocol, $callback);
        $this->registerEventHandlers('open');
    }

    public function getOpenHandlers(string $protocolAction): ?array
    {
        return $this->openHandlers[$protocolAction]?->toArray();
    }

    public function hasOpenHandlers(string $protocolAction): bool
    {
        return $this->openHandlers[$protocolAction]->offsetExists($protocolAction) && $this->openHandlers[$protocolAction]->count() > 0;
    }

    public function removeOpenHandlers(string $protocolAction): void
    {
        $this->openHandlers[$protocolAction]?->clear();
        unset($this->openHandlers[$protocolAction]);
        $this->registerEventHandlers('open');
    }

    public function handleOpenEvent(Server $server, int $fd, int $reactorId): void
    {
        $this?->logger?->debug("Handling open event for fd {$fd}");
        $eventHandlers = $this->getEventActionHandlers('open', null);
        foreach ($eventHandlers as $callback) {
            $callback($server, $fd, $reactorId);
        }
    }

    public function registerDisconnectHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->disconnectHandlers[$protocolAction]) || !($this->disconnectHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->disconnectHandlers[$protocolAction] = new CallableCollection();
        }
        $this->disconnectHandlers[$protocolAction]->offsetSet($protocol, $callback);
        $this->registerEventHandlers('disconnect');
    }

    public function getDisconnectHandlers(string $protocolAction): ?array
    {
        return $this->disconnectHandlers[$protocolAction]?->toArray();
    }

    public function hasDisconnectHandlers(string $protocolAction): bool
    {
        return $this->disconnectHandlers[$protocolAction]->offsetExists($protocolAction) && $this->disconnectHandlers[$protocolAction]->count() > 0;
    }

    public function removeDisconnectHandlers(string $protocolAction): void
    {
        $this->disconnectHandlers[$protocolAction]?->clear();
        unset($this->disconnectHandlers[$protocolAction]);
        $this->registerEventHandlers('disconnect');
    }

    public function handleDisconnectEvent(Server $server, int $fd, int $reactorId): void
    {
        $this?->logger?->debug("Handling disconnect event for fd {$fd}");
        $eventHandlers = $this->getEventActionHandlers('disconnect', null);
        foreach ($eventHandlers as $callback) {
            $callback($server, $fd, $reactorId);
        }
    }

}