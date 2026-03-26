<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Hamum;

use Psr\Log\LoggerInterface;
use Swoole\Server;
use Tabula17\Satelles\Nexus\Utilis\Server\Trait\HamumTrait;
use Tabula17\Satelles\Utilis\Collection\CallableCollection;
use Tabula17\Satelles\Utilis\Config\TCPServerConfig;

abstract class Basis extends Server implements HamumServerInterface
{
    use HamumTrait;

    private array $connectHandlers = [];
    private array $receiveHandlers = [];
    private array $packetHandlers = [];

    public function __construct(TCPServerConfig $config, public ?LoggerInterface $logger = null)
    {
        parent::__construct($config->host, $config->port, $config->mode ?? SWOOLE_BASE, $config->type ?? SWOOLE_SOCK_TCP);
        $sslEnabled = isset($config->ssl) && $config->ssl->enabled;
        $options = $sslEnabled ? array_merge($config->options, $config->ssl->toArray()) : $config->options;
        if ($sslEnabled) {
            unset($options['enabled']);
        }
        $this->set($options);
        $this->init();
    }
    abstract protected function init(): void;


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
            $eventHandlers = array_merge($this->getEventActionHandlers('receive', $protocolAction) , $this->getEventActionHandlers('receive', '*'));
            foreach ($eventHandlers as $callback) {
                $callback($server, $fd, $reactorId, $data);
            }
        }
    }
    public function registerPacketHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->packetHandlers[$protocolAction]) || !($this->packetHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->packetHandlers[$protocolAction] = new CallableCollection();
        }
        $this->packetHandlers[$protocolAction]->offsetSet($protocol, $callback);
        $this->registerEventHandlers('packet');
    }

    public function getPacketHandlers(string $protocolAction): ?array
    {
        return $this->packetHandlers[$protocolAction]?->toArray();
    }

    public function hasPacketHandlers(string $protocolAction): bool
    {
        return $this->packetHandlers[$protocolAction]->offsetExists($protocolAction) && $this->packetHandlers[$protocolAction]->count() > 0;
    }

    public function removePacketHandlers(string $protocolAction): void
    {
        $this->packetHandlers[$protocolAction]?->clear();
        unset($this->packetHandlers[$protocolAction]);
        $this->registerEventHandlers('packet');
    }

    public function handlePacketEvent(Server $server, string $data, array $clientInfo): void
    {
        $this?->logger?->debug("Handling packet event with data: {$data}");
        $protocolAction = json_validate($data) ? json_decode($data, true)['action'] : '';
        if ($this->hasPacketHandlers($protocolAction)) {
            $eventHandlers = array_merge($this->getEventActionHandlers('packet', $protocolAction) , $this->getEventActionHandlers('packet', '*'));
            foreach ($eventHandlers as $callback) {
                $callback($server, $data, $clientInfo);
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

}