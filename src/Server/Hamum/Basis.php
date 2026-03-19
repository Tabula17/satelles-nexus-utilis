<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Hamum;

use Psr\Log\LoggerInterface;
use Swoole\Server;
use Swoole\Server\Task;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerCollection;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Action;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Type;
use Tabula17\Satelles\Utilis\Collection\CallableCollection;

class Basis extends Server
{
    private array $hookedEvents = [];
    private ?ProtocolManagerCollection $protocolManagers;
    private array $pipeMessageHandlers;
    private array $taskHandlers = [];
    private array $finishHandlers = [];
    private array $receiveHandlers = [];
    private array $packetHandlers = [];
    private array $connectHandlers = [];
    private array $closeHandlers = [];

    public ?LoggerInterface $logger {
        set {
            $this->logger = $value;
        }
    }

    /**
     * Registers a callback to be executed when a specific event occurs.
     * @param string $event_name
     * @param callable $callback
     * @param bool $cleanQueue
     * @return bool
     */
    public function on(string $event_name, callable $callback, bool $cleanQueue = false): bool
    {
        if ($event_name === 'task') {
            $this->logger?->debug("Registering task event handler. PID: " . getmypid() . " WID: " . $this->worker_id);
            $this->logger?->notice("Task event handlers need to be registered for each protocol action. Registering handler for all actions with protocol 'generic'.");
            $this->logger?->notice("This is not recommended, as it will result in duplicate handlers for each protocol action. Consider registering task handlers for specific protocol actions instead of using the wildcard '*'.");
            $this->logger?->notice("If inside 'callback' execute '\$task->finish()' or '\$server->finish()' to send a response to the client or task worker all remaining tasks subscribed to same protocol action will be stopped. ");
            $this->registerTaskHandlers('*', $callback); // protocol 'generic' is default, so we don't need to register it'!
            return true;
        }

        if ($cleanQueue || !isset($this->hookedEvents[$event_name])) {
            $this->logger?->debug("Cleaning queue for event $event_name. PID: " . getmypid() . " WID: " . $this->worker_id);
            $this->hookedEvents[$event_name] = [];
        }
        $this->logger?->debug("Registering callback for event $event_name. PID: " . getmypid() . " WID: " . $this->worker_id);
        $this->logger?->debug("Callbacks registered so far: " . count($this->hookedEvents[$event_name]));


        $this->hookedEvents[$event_name][] = $callback;
        $hookedEventCallbacks = &$this->hookedEvents[$event_name];

        $hookedCallback = function (...$args) use (&$hookedEventCallbacks, $event_name) {
            $this->logger?->debug("Executing callback for event {$event_name}. PID: " . getmypid() . " WID: " . $this->worker_id);
            foreach ($hookedEventCallbacks as $callback) {
                $callback(...$args);
            }
        };
        if (strtolower($event_name) === 'beforestart') {
            //swoole doesn't allow hooking beforeStart event, so we handle it internally and skip registering it in swoole's event system
            return true;
        }
        return parent::on($event_name, $hookedCallback);
    }

    /**
     * Unregisters a callback or all callbacks associated with a specific event.
     *
     * @param string $event_name The name of the event to modify.
     * @param callable|null $callback The specific callback to remove. If null, all callbacks for the event will be removed.
     * @return bool Returns true if the event or callback was successfully unregistered, false otherwise.
     */
    public function off(string $event_name, ?callable $callback): bool
    {
        if (isset($this->hookedEvents[$event_name])) {
            if (isset($callback)) {
                $this->hookedEvents[$event_name] = array_filter($this->hookedEvents[$event_name], static fn($c) => $c !== $callback);
            } else {
                unset($this->hookedEvents[$event_name]);
                parent::on($event_name, static fn() => null);
            }
            return true;
        }
        return false;
    }

    /**
     * Starts the server and handles preparatory operations before the server begins running.
     *
     * @return bool Returns true if the server started successfully, false otherwise.
     */
    public function start(): bool
    {
        // Hooking beforeStart event is not possible through Swoole's event system, so we handle it internally before starting the server
        $this->beforeStarting();

        return parent::start();
    }

    private function beforeStarting(): void
    {
        $this->logger?->info('Starting server->onBeforeStart');
        $this->logger?->debug('Server settings: ' . json_encode($this->setting));

        $this->on('workerStart', function (Server $server, int $workerId) {
            $this->logger?->debug('👷🏼‍♀️🏁 Starting worker ' . $workerId . ', checking hooked events for workerStart');
        });
        if (isset($this->protocolManagers) && $this->protocolManagers->count() > 0) {
            $this?->logger?->debug('🎬 Starting server->onBeforeStart with protocol managers');
            /** @var ProtocolManagerInterface $protocolManager */
            foreach ($this->protocolManagers as $protocolManager) {
                $this?->logger?->debug('Initializing protocol manager ' . $protocolManager->protocol->name);
                $protocolManager->initializeOnStart($this);
            }
            $this->on('workerStart', function (Server $server, int $workerId) {
                $this->logger?->debug('👷🏼‍♀️🏁 Starting worker ' . $workerId . ', checking protocol managers');
                foreach ($this->protocolManagers as $protocolManager) {
                    $this?->logger?->debug('👷🏼‍♀️🎬 Initializing protocol manager ' . $protocolManager->protocol->name . ' on worker ' . $workerId);
                    /** @var ProtocolManagerInterface $protocolManager */
                    $protocolManager->initializeOnWorkers($server, $workerId);
                }
            });

            $this->on('workerStop', function (Server $server, int $workerId) {
                foreach ($this->protocolManagers as $protocolManager) {
                    $this?->logger?->debug('👷🏼‍♀️🧹 Cleaning up protocol manager ' . $protocolManager->protocol->name . ' on worker ' . $workerId);
                    /** @var ProtocolManagerInterface $protocolManager */
                    $protocolManager->cleanUpResources($server, $workerId);
                }
            });
            $this->on('beforeShutdown', function (Server $server) {
                foreach ($this->protocolManagers as $protocolManager) {
                    $this?->logger?->debug('📴 Shutting down. 🧹 Cleaning up protocol manager ' . $protocolManager->protocol->name . ' on worker ' . $server->worker_id);
                    /** @var ProtocolManagerInterface $protocolManager */
                    $protocolManager->cleanUpResources($server, $server->worker_id);
                }
            });

        }


        if ($this->setting['task_enable_coroutine'] ?? false) {
            $this?->logger?->debug('Task workers will run in coroutine mode');
            $this->on('task', function (Server $server, Task $task) {
                $data = $task->data;
                $taskAction = json_validate($data) ? json_decode($data, true)['action'] : '';
                $results = [];

                $taskHandlers = array_merge($this->taskHandlers[$taskAction]->toArray() ?? [], $this->taskHandlers['*']->toArray() ?? []);
                $this?->logger?->debug("Found task handlers for action {$taskAction}");
                foreach ($taskHandlers as $protocol => $handler) {
                    $this?->logger?->debug("Handling task for action {$taskAction} [{$protocol}] with data: {$data}");
                    $results[$protocol] = $handler($server, $data);
                }

                $output = [
                    'action' => $taskAction,
                    'results' => $results,
                    'protocols' => array_keys($results),
                    'worker_id' => $server->worker_id,
                    'task_id' => $task->id,
                    'task_worker_id' => $task->worker_id,
                    'time' => microtime(true),

                ];
                $task->finish(json_encode($output));
            });
        } else {
            $this?->logger?->debug('Task workers will run in non-coroutine mode');
            $this->on('task', function (Server $server, int $taskId, int $workerId, string $data) {
                $taskAction = json_validate($data) ? json_decode($data, true)['action'] : '';
                $results = [];
                $taskHandlers = array_merge($this->taskHandlers[$taskAction]->toArray() ?? [], $this->taskHandlers['*']->toArray() ?? []);
                $this?->logger?->debug("Found task handlers for action {$taskAction}");
                foreach ($taskHandlers as $protocol => $handler) {
                    $this?->logger?->debug("Handling task for action {$taskAction} [{$protocol}] with data: {$data}");
                    $results[$protocol] = $handler($server, $data);
                }
                $output = [
                    'action' => $taskAction,
                    'results' => $results,
                    'protocols' => array_keys($results),
                    'worker_id' => $workerId,
                    'task_id' => $taskId,
                    'task_worker_id' => $server->worker_id,
                    'time' => microtime(true),
                ];
                $server->finish(json_encode($output));
            });
        }
        $this->on('finish', function (Server $server,  int $taskId, string $data) {
            $taskAction = json_validate($data) ? json_decode($data, true)['action'] : '';
            $handlers = array_merge($this->finishHandlers[$taskAction]->toArray() ?? [], $this->finishHandlers['*']->toArray() ?? []);
            foreach ($handlers as $protocol => $handler) {
                $this?->logger?->debug("Handling finish for action '$taskAction' [{$protocol}] with data: {$data}");
                $handler($server, $taskId, $data);
            }
        });

        $this->on('pipeMessage', function (Server $server, int $src_worker_id, string $data) {
            $taskAction = json_validate($data) ? json_decode($data, true)['action'] : '';
            $handlers = array_merge($this->pipeMessageHandlers[$taskAction]->toArray() ?? [], $this->pipeMessageHandlers['*']->toArray() ?? []);
            foreach ($handlers as $protocol => $handler) {
                $this?->logger?->debug("Handling finish for action '$taskAction' [{$protocol}] with data: {$data}");
                $handler($server, $src_worker_id, $data);
            }
        });
        /**
         * The difference between Http and Server's callbacks is:
         * Http\Server->on does not support setting callbacks for onConnect/onReceive
         * Http\Server->on also supports a new event type onRequest, which triggers when a client request is received.
         */
        $this->on('connect', function (Server $server, int $fd, int $reactorId) {
            $this?->logger?->debug("Client connected: {$fd} from {$reactorId}");
            if (isset($this->protocolManagers) && $this->protocolManagers->count() > 0) {
                foreach ($this->protocolManagers as $protocolManager) {
                    $this?->logger?->debug("Checking protocol manager {$protocolManager->protocol->name} for connect event");
                    $protocolManager->runOnOpenConnection($server, $fd, $reactorId);
                }
            }
            $handlers = array_merge(...array_values(array_map(static fn($collection) => $collection->toArray(), ($this->connectHandlers))));
            foreach($handlers as $handler) {
                $this?->logger?->debug("Handling connect event with handler for protocol");
                $handler($server, $fd, $reactorId);
            }
        });
        $this->on('close', function (Server $server, int $fd, int $reactorId) {
            $this?->logger?->debug("Client connected: {$fd} from {$reactorId}");
            if (isset($this->protocolManagers) && $this->protocolManagers->count() > 0) {
                foreach ($this->protocolManagers as $protocolManager) {
                    $this?->logger?->debug("Checking protocol manager {$protocolManager->protocol->name} for connect event");
                    $protocolManager->runOnCloseConnection($server, $fd, $reactorId);
                }
            }
            $handlers = array_merge(...array_values(array_map(static fn($collection) => $collection->toArray(), ($this->closeHandlers))));
            foreach($handlers as $handler) {
                $this?->logger?->debug("Handling connect event with handler for protocol");
                $handler($server, $fd, $reactorId);
            }
        });
        $this->on('receive', function (Server $server, int $fd, int $reactorId, string $data) {
            $this?->logger?->debug("Received data: {$data} from {$fd} from {$reactorId}");
            $handlers = array_merge(...array_values(array_map(static fn($collection) => $collection->toArray(), ($this->receiveHandlers))));
            foreach($handlers as $handler) {
                $this?->logger?->debug("Handling receive event with handler for protocols");
                $handler($server, $fd, $reactorId, $data);
            }
        });
        $this->on('packet', function (Server $server, string $data, array $clientInfo) {
            $this?->logger?->debug("Received packet: {$data} from {$clientInfo['address']}:{$clientInfo['port']}");
            $handlers = array_merge(...array_values(array_map(static fn($collection) => $collection->toArray(), ($this->packetHandlers))));
            foreach($handlers as $handler) {
                $this?->logger?->debug("Handling receive event with handler for protocols");
                $handler($server, $data, $clientInfo);
            }
        });

        foreach ($this->hookedEvents['beforeStart'] ?? [] as $k => $callback) {
            $this?->logger?->debug('Executing callback ' . $k . ' on server->beforeStart');
            if (is_callable($callback)) {
                $callback($this);
            } else {
                unset($this->hookedEvents['beforeStart'][$k]);
            }
        }
        $this?->logger?->debug('Server->beforeStart executed');
        $this?->logger?->debug(str_repeat('-', 100));
    }

    /*public function beforeShuttingDown(): void
    {
        $this?->logger?->info('Starting server->beforeShuttingDown');
    }*/
    public function getActionsByProtocol(?string $protocol): array
    {
        $actions = [];
        /** @var ProtocolManagerInterface $protocolManager */
        foreach ($this->protocolManagers as $protocolManager) {
            $actions[$protocolManager->protocol::getProtocolName()] = $protocolManager->protocol->toArray();
        }
        return isset($protocol) ? ($actions[$protocol] ?? []) : $actions;
    }

    public function getResponseTypesByProtocol(?string $protocol): array
    {
        $types = [];
        /** @var ProtocolManagerInterface $protocolManager */
        foreach ($this->protocolManagers as $protocolManager) {
            $types[$protocolManager->protocol::getProtocolName()] = $protocolManager->responses->toArray();
        }
        return isset($protocol) ? ($types[$protocol] ?? []) : $types;
    }

    public function getProtocolsByActions(?string $action): array
    {
        $protocols = [];
        /** @var ProtocolManagerInterface $protocolManager */
        foreach ($this->protocolManagers as $protocolManager) {
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
        if (!isset($this->protocolManagers)) {
            $this->protocolManagers = new ProtocolManagerCollection();
        }
        $this->protocolManagers->offsetSet($protocol, $manager);
    }

    public function getProtocolManager(string $protocol): ?ProtocolManagerInterface
    {
        return $this->protocolManagers?->offsetGet($protocol);
    }

    public function hasProtocolManager(string $protocol): bool
    {
        return $this->protocolManagers->offsetExists($protocol);
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
        $this->protocolManagers->offsetGet($protocol)->cleanUpResources();
        $this->protocolManagers->offsetUnset($protocol);
    }

    public function registerPipeMessageHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->pipeMessageHandlers[$protocolAction]) || !($this->pipeMessageHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->pipeMessageHandlers[$protocolAction] = new CallableCollection();
        }
        $this->pipeMessageHandlers[$protocolAction]->offsetSet($protocol, $callback);
    }

    public function getPipeMessageHandlers(string $protocolAction): ?array
    {
        return $this->pipeMessageHandlers[$protocolAction]?->toArray();
    }

    public function hasPipeMessageHandlers(string $protocolAction): bool
    {
        return $this->pipeMessageHandlers[$protocolAction]->offsetExists($protocolAction) && $this->pipeMessageHandlers[$protocolAction]->count() > 0;
    }

    public function removePipeMessageHandlers(string $protocolAction): void
    {
        $this->pipeMessageHandlers[$protocolAction]?->clear();
        unset($this->pipeMessageHandlers[$protocolAction]);
    }

    public function registerTaskHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->taskHandlers[$protocolAction]) || !($this->taskHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->taskHandlers[$protocolAction] = new CallableCollection();
        }
        $this->taskHandlers[$protocolAction]->offsetSet($protocol, $callback);
    }

    public function getTaskHandlers(string $protocolAction): ?array
    {
        return $this->taskHandlers[$protocolAction]?->toArray();
    }

    public function hasTaskHandlers(string $protocolAction): bool
    {
        return isset($this->taskHandlers[$protocolAction]) && $this->taskHandlers[$protocolAction]->count() > 0;
    }

    public function removeTaskHandlers(string $protocolAction): void
    {
        $this->taskHandlers[$protocolAction]?->clear();
        unset($this->taskHandlers[$protocolAction]);
    }

    public function registerFinishHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->finishHandlers[$protocolAction]) || !($this->finishHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->finishHandlers[$protocolAction] = new CallableCollection();
        }
        $this->finishHandlers[$protocolAction]->offsetSet($protocol, $callback);
    }

    public function getFinishHandlers(string $protocolAction): ?array
    {
        return $this->finishHandlers[$protocolAction]?->toArray();
    }

    public function hasFinishHandlers(string $protocolAction): bool
    {
        return $this->finishHandlers[$protocolAction]->offsetExists($protocolAction) && $this->finishHandlers[$protocolAction]->count() > 0;
    }

    public function removeFinishHandlers(string $protocolAction): void
    {
        $this->finishHandlers[$protocolAction]?->clear();
        unset($this->finishHandlers[$protocolAction]);
    }

    public function registerReceiveHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->receiveHandlers[$protocolAction]) || !($this->receiveHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->receiveHandlers[$protocolAction] = new CallableCollection();
        }
        $this->receiveHandlers[$protocolAction]->offsetSet($protocol, $callback);
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
    }

    public function registerPacketHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->packetHandlers[$protocolAction]) || !($this->packetHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->packetHandlers[$protocolAction] = new CallableCollection();
        }
        $this->packetHandlers[$protocolAction]->offsetSet($protocol, $callback);
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
    }

    public function registerConnectHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->connectHandlers[$protocolAction]) || !($this->connectHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->connectHandlers[$protocolAction] = new CallableCollection();
        }
        $this->connectHandlers[$protocolAction]->offsetSet($protocol, $callback);
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
    }

    public function registerCloseHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->closeHandlers[$protocolAction]) || !($this->closeHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->closeHandlers[$protocolAction] = new CallableCollection();
        }
        $this->closeHandlers[$protocolAction]->offsetSet($protocol, $callback);
    }

    public function getCloseHandlers(string $protocolAction): ?array
    {
        return $this->closeHandlers[$protocolAction]?->toArray();
    }

    public function hasCloseHandlers(string $protocolAction): bool
    {
        return $this->closeHandlers[$protocolAction]->offsetExists($protocolAction) && $this->closeHandlers[$protocolAction]->count() > 0;
    }

    public function removeCloseHandlers(string $protocolAction): void
    {
        $this->closeHandlers[$protocolAction]?->clear();
        unset($this->closeHandlers[$protocolAction]);
    }
}