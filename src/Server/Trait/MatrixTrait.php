<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Trait;

use Psr\Log\LoggerInterface;
use Swoole\Server;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerCollection;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Action;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Type;

trait MatrixTrait
{

    public ?LoggerInterface $logger;
    private ?ProtocolManagerCollection $protocolManagers;

    public function getActionsByProtocol(?string $protocol): array
    {
        $actions = [];
        /** @var ProtocolManagerInterface $protocolManager */
        foreach ($this->getProtocolManagers() as $protocolManager) {
            $actions[$protocolManager->protocol::getProtocolName()] = $protocolManager->protocol->toArray();
        }
        return isset($protocol) ? ($actions[$protocol] ?? []) : $actions;
    }

    public function getResponseTypesByProtocol(?string $protocol): array
    {
        $types = [];
        /** @var ProtocolManagerInterface $protocolManager */
        foreach ($this->getProtocolManagers() as $protocolManager) {
            $types[$protocolManager->protocol::getProtocolName()] = $protocolManager->responses->toArray();
        }
        return isset($protocol) ? ($types[$protocol] ?? []) : $types;
    }

    public function getProtocolsByActions(?string $action): array
    {
        $protocols = [];
        /** @var ProtocolManagerInterface $protocolManager */
        foreach ($this->getProtocolManagers() as $protocolManager) {
            foreach ($protocolManager->protocol->toArray() as $protocolAction) {
                if (!isset($protocols[$protocolAction])) {
                    $protocols[$protocolAction] = [];
                }
                $protocols[$protocolAction][] = $protocolManager->protocol::getProtocolName();
            }
        }
        return isset($action) ? ($protocols[$action] ?? []) : $protocols;
    }

    public function addProtocolManager(string $protocol, ProtocolManagerInterface $manager): void
    {
        $this->logger?->debug("Adding protocol manager for protocol {$protocol}");
        $this->getProtocolManagers()->offsetSet($protocol, $manager);
        if ($this instanceof Server) {
            $this->logger?->debug("Registering protocol manager events");
            if ($this instanceof HamumServerInterface) {
                $this->logger?->debug("Registering protocol manager events for HamumServer");
                $this->on('beforestart', $manager->initializeOnStart(...));
                $this->on('beforestart', $manager->registerProtocolHandlers(...));
            }
            $this->on('workerStart', $manager->initializeOnWorkers(...));
            $this->on('workerStop', $manager->cleanUpResources(...));
            $this->on('open', $manager->runOnOpenConnection(...));
            $this->on('close', $manager->runOnCloseConnection(...));
            $this->on('beforeshutdown', $manager->cleanUpResources(...));
        }
    }

    public function getProtocolManager(string $protocol): ?ProtocolManagerInterface
    {
        return $this->getProtocolManagers()?->offsetGet($protocol);
    }

    public function hasProtocolManager(string $protocol): bool
    {
        return $this->getProtocolManagers()->offsetExists($protocol);
    }

    public function getRequestProtocol(string $protocol): ?Action
    {
        return $this->getProtocolManager($protocol)?->protocol;
    }

    public function getResponseTypes(string $protocol): ?Type
    {
        return $this->getProtocolManager($protocol)?->responses;
    }

    public function removeProtocolManager(string $protocol): void
    {
        $this->getProtocolManagers()->offsetGet($protocol)->cleanUpResources();
        $this->getProtocolManagers()->offsetUnset($protocol);
    }

    public function getProtocolManagers(): ProtocolManagerCollection
    {
        if (!isset($this->protocolManagers)) {
            $this->protocolManagers = new ProtocolManagerCollection();
        }
        return $this->protocolManagers;
    }
}