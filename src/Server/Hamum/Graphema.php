<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Hamum;

use Psr\Log\LoggerInterface;
use Swoole\Http\Server;
use Tabula17\Satelles\Nexus\Utilis\Server\Trait\HamumTrait;
use Tabula17\Satelles\Utilis\Collection\CallableCollection;
use Tabula17\Satelles\Utilis\Config\TCPServerConfig;

abstract class Graphema extends Server implements HamumServerInterface
{
    use HamumTrait;
    private array $requestHandlers = [];

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

    public function handleRequestEvent(string $protocolAction, $request, $response): void
    {
        $this?->logger?->debug("Handling request event with protocolAction: {$protocolAction}");
        $eventHandlers = array_merge($this->getEventActionHandlers('request', $protocolAction) , $this->getEventActionHandlers('request', '*'));
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
}