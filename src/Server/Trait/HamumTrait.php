<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Trait;

use Psr\Log\LoggerInterface;
use Swoole\Server as TcpUdpServer;
use Swoole\Http\Server as HttpServer;
use Swoole\WebSocket\Server as WebSocketServer;
use Swoole\Server\Task;
use Tabula17\Satelles\Utilis\Collection\CallableCollection;

trait HamumTrait
{
    use MatrixTrait;

    private array $hookedEvents = [];
    /**
     * @var array With actionable protocols as keys and callables as values. Tasks event occurs in every Swoole server worker and Server type.
     */
    private array $taskHandlers = [];
    /**
     * @var array With actionable protocols as keys and callables as values. Finish event occurs in every Swoole server worker and Server type having task workers enabled.
     */
    private array $finishHandlers = [];

    private array $pipeMessageHandlers = [];
    // private array $receiveHandlers = [];
    // private array $packetHandlers = [];
    // private array $connectHandlers = [];
    private array $closeHandlers = [];
    public ?LoggerInterface $logger;

    private array $registeredHandlers = [];
    private array $definedHandlers = [];

    private function traitAllowed(): bool
    {
        return $this instanceof TcpUdpServer;
    }

    private function eventsSupported(string $event_name): bool
    {
        $event_name = strtolower($event_name);
        $baseEvents = [
            'beforestart', // internal event! dont exists in swoole, but we handle it internally in HamumTrait
            'start',
            'beforeshutdown',
            'shutdown',
            'workerstart',
            'workerstop',
            'workerexit',
            'task',
            'finish',
            'pipemessage',
            'workererror',
            'managerstart',
            'managerstop',
            'beforereload',
            'afterreload'
        ];
        $tcpEvents = [
            'close',
            'packet',
            'receive',
            'connect'
        ];
        //not onConnect/onReceive
        //add onRequest
        $httpEvents = [
            'close',
            'request',
        ];
        //add onBeforeHandshakeResponse, onHandShake, onMessage, onOpen, onDisconnect
        $websocketEvents = [
            'beforehandshakerequest',
            'handshake',
            'message',
            'open',
            'receive',
            'connect',
            'disconnect'
        ];

        if ($this instanceof WebSocketServer) {
            $allEvents = array_merge($baseEvents, $httpEvents, $websocketEvents);
        } elseif ($this instanceof HttpServer) {
            $allEvents = array_merge($baseEvents, $httpEvents);
        } elseif ($this instanceof TcpUdpServer) {
            $allEvents = array_merge($baseEvents, $tcpEvents);
        } else {
            return false;
        }

        return in_array($event_name, $allEvents, true);
    }

    abstract protected function onBeforeStart(): void;

    public function start(): bool
    {
        if (!$this->traitAllowed()) {
            $this->logger?->error("Server type is not supported by this trait. Can't override start() method.");
            $this?->logger?->debug(str_repeat('-', 100));
            return false;
        }


        $vars = get_class_vars(get_class($this));
        foreach ($vars as $key => $value) {
            $this->logger?->debug("Finding handlers on properties -> property: $key");
            if (str_ends_with($key, 'Handlers')) {
                $event_name = strtolower(str_replace('Handlers', '', $key));
                $this->definedHandlers[$event_name] = [
                    'property' => $key,
                    'handler' => "handle" . ucfirst($event_name) . "Event"
                ];
            }
        }


        $this->onBeforeStart();
        $this->logger?->debug('Server->Template fn->onBeforeStart executed. Now look for callbacks on server->beforestart');

        foreach ($this->hookedEvents['beforestart'] ?? [] as $k => $callback) {
            $this?->logger?->debug('Executing callback ' . $k . ' on server->beforestart');
            if (is_callable($callback)) {
                $callback($this);
            } else {
                unset($this->hookedEvents['beforestart'][$k]);
            }
        }
        if ($this->setting['task_worker_num'] > 0 && empty($this->taskHandlers)) {
            $this?->logger?->debug('No task handlers found. Registering task event handler.');
            $this->on('task', $this->handleTaskEvent(...));
        }

        $this?->logger?->debug('Server->beforestart executed');
        $this?->logger?->debug(str_repeat('-', 100));
        return parent::start();
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
        if (!$this->traitAllowed()) {
            $this->logger?->error("Server type is not supported by this trait. Can't override on() method.");
            return false;
        }
        $event_name = strtolower($event_name);
        $hookedCallback = $this->onEvent($event_name, $callback, $cleanQueue, $this->worker_id);
        if (is_bool($hookedCallback)) {
            return $hookedCallback;
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
        $off = $this->offEvent($event_name, $callback);
        if (is_callable($off)) {
            if ($this->traitAllowed()) {
                parent::on($event_name, $off);
            }
            return true;
        }
        return $off;
    }

    private function onEvent(string $event_name, callable $callback, bool $cleanQueue = false, int $worker_id = -1): bool|callable|null
    {
        if (!$this->eventsSupported($event_name)) {
            $this->logger?->error("Event '$event_name' is not supported by this server type.");
            return false;
        }
        if ($event_name === 'task') {
            $this->logger?->debug("Registering task event handler. PID: " . getmypid() . " WID: " . $worker_id);
            $this->logger?->notice("Task event handlers need to be registered for each protocol action. Registering handler for all actions with protocol 'generic'.");
            $this->logger?->notice("This is not recommended, as it will result in duplicate handlers for each protocol action. Consider registering task handlers for specific protocol actions instead of using the wildcard '*'.");
            $this->logger?->notice("If inside 'callback' execute '\$task->finish()' or '\$server->finish()' to send a response to the client or task worker all remaining tasks subscribed to same protocol action will be stopped. ");
            $this->registerTaskHandlers('*', $callback); // protocol 'generic' is default, so we don't need to register it'!
            return true;
            //$this->logger?->critical("🧨 Task event handler registered. PID: " . getmypid() . " WID: " . $worker_id);
        }

        if ($cleanQueue || !isset($this->hookedEvents[$event_name])) {
            if ($cleanQueue) {
                $this->logger?->debug("Cleaning queue for event $event_name. PID: " . getmypid() . " WID: " . $worker_id);
            }
            $this->hookedEvents[$event_name] = [];
        }
        $this->logger?->debug("Registering callback for event *** $event_name ***. PID: " . getmypid() . " WID: " . $worker_id);
        // $this->logger?->debug("Callbacks registered so far: " . count($this->hookedEvents[$event_name]));

        return $this->registerHookedCallback($event_name, $callback);
    }

    private function registerHookedCallback(string $event_name, callable $callback): bool|callable
    {
        $this->hookedEvents[$event_name][] = $callback;
        $hookedCallback = function (...$args) use ($event_name) {
            foreach ($this->hookedEvents[$event_name] as $callback) {
                $callback(...$args);
            }
        };
        if (strtolower($event_name) === 'beforestart') {
            //swoole doesn't allow hooking beforestart event, so we handle it internally and skip registering it in swoole's event system
            return true;
        }
        return $hookedCallback;
    }

    private function registerEventHandlers(string $event_name): void
    {
        $this->logger?->debug("Registering event handlers for event $event_name");
        if (isset($this->definedHandlers[strtolower($event_name)])) {
            $this->logger?->debug("Event $event_name is defined. Registering event handlers.");
            $handlerName = $this->definedHandlers[strtolower($event_name)]['handler'];
            $property = $this->definedHandlers[strtolower($event_name)]['property'];
            $handlers = count($this->$property);
            $this->logger?->debug("Handlers found: $handlers on property $property.");
            if (!method_exists($this, $handlerName)) {
                $this->logger?->error("Method $handlerName does not exist on class " . get_class($this));
            }
            if ($handlers > 0 && !isset($this->registeredHandlers[$event_name]) && method_exists($this, $handlerName)) {
                $this->logger?->debug("Registering event handlers for event $event_name. Handlers found: $handlers");
                $this->registeredHandlers[$event_name] = true;
                $this->on($event_name, $this->$handlerName(...));
            }
            if ($handlers === 0 && isset($this->registeredHandlers[$event_name])) {
                $this->logger?->debug("No handlers found for event $event_name. Unregistering event handler.");
                unset($this->registeredHandlers[$event_name]);
                $this->off($event_name, $this->$handlerName(...));
            }
        }

    }

    private function getEventActionHandlers(string $event_name, ?string $action): array
    {
        $vars = get_class_vars(get_class($this));
        $handlers = [];
        if (isset($this->definedHandlers[strtolower($event_name)])) {
            $property = $this->definedHandlers[strtolower($event_name)]['property'];
            $handlers = array_merge(...array_values(array_map(static fn($collection) => $collection->toArray(), ($this->$property ?? [new CallableCollection()]))));
        }
        return $action ? $handlers[$action] : $handlers;
    }


    /**
     * Unregisters a callback or all callbacks associated with a specific event.
     *
     * @param string $event_name The name of the event to modify.
     * @param callable|null $callback The specific callback to remove. If null, all callbacks for the event will be removed.
     * @return bool|callable Returns true if the event or callback was successfully unregistered, false otherwise.
     */
    private function offEvent(string $event_name, ?callable $callback): bool|callable
    {
        if (isset($this->hookedEvents[$event_name])) {
            if (isset($callback)) {
                $this->hookedEvents[$event_name] = array_filter($this->hookedEvents[$event_name], static fn($c) => $c !== $callback);
            } else {
                unset($this->hookedEvents[$event_name]);
                return static fn() => null;
            }
            return true;
        }
        return false;
    }

    // TASK HANDLERS ->
    public function registerTaskHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->taskHandlers[$protocolAction]) || !($this->taskHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->taskHandlers[$protocolAction] = new CallableCollection();
        }
        $this->taskHandlers[$protocolAction]->offsetSet($protocol, $callback);
        parent::on('Task', $this->handleTaskEvent(...));
    }

    public function getTaskHandlers(string $protocolAction): ?array
    {
        return $this->getEventActionHandlers('task', $protocolAction); //$this->taskHandlers[$protocolAction]?->toArray();
    }

    public function hasTaskHandlers(string $protocolAction): bool
    {
        return isset($this->taskHandlers[$protocolAction]) && $this->taskHandlers[$protocolAction]->count() > 0;
    }

    public function removeTaskHandlers(string $protocolAction): void
    {
        $this->taskHandlers[$protocolAction]?->clear();
        unset($this->taskHandlers[$protocolAction]);
        if ($this->setting['task_worker_num'] > 0) {
            parent::on('Task', $this->handleTaskEvent(...));
        }
    }

    private function handleTaskEvent(...$args): void
    {
        if ($this instanceof TcpUdpServer && $this->setting['task_enable_coroutine']) {
            $this?->logger?->debug('Task workers will run in coroutine mode');
            /** @var TcpUdpServer $server */
            /** @var Task $task */
            [$server, $task] = $args;
            $data = $task->data;
            $taskAction = json_validate($data) ? json_decode($data, true)['action'] : '';
            $results = [];
            $results['raw'] = $data;
            $taskHandlers = array_merge($this->getEventActionHandlers('task', $taskAction), $this->getEventActionHandlers('task', '*'));
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
            $this?->logger?->debug("Task finished with output: " . json_encode($output));
            $task->finish(json_encode($output));
        } else {
            /** @var TcpUdpServer $server */
            /** @var int $taskId */
            /** @var int $workerId */
            /** @var string $data */
            [$server, $taskId, $workerId, $data] = $args;
            $this?->logger?->debug('Task workers will run in non-coroutine mode');
            $taskAction = json_validate($data) ? json_decode($data, true)['action'] : '';
            $results = [];
            $results['raw'] = $data;
            $taskHandlers = array_merge($this->getEventActionHandlers('task', $taskAction), $this->getEventActionHandlers('task', '*'));
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
        }
    }

    // FINISH HANDLERS ->
    public function registerFinishHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->finishHandlers[$protocolAction]) || !($this->finishHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->finishHandlers[$protocolAction] = new CallableCollection();
        }
        $this->finishHandlers[$protocolAction]->offsetSet($protocol, $callback);
        $this->registerEventHandlers('finish');
    }

    public function getFinishHandlers(string $protocolAction): ?array
    {
        return $this->getEventActionHandlers('finish', $protocolAction); //$this->finishHandlers[$protocolAction]?->toArray();
    }

    public function hasFinishHandlers(string $protocolAction): bool
    {
        return $this->finishHandlers[$protocolAction]->offsetExists($protocolAction) && $this->finishHandlers[$protocolAction]->count() > 0;
    }

    public function removeFinishHandlers(string $protocolAction): void
    {
        $this->finishHandlers[$protocolAction]?->clear();
        unset($this->finishHandlers[$protocolAction]);
        $this->registerEventHandlers('finish');
    }

    public function handleFinishEvent(TcpUdpServer $server, int $taskId, string $data): void
    {
        $protocolAction = json_validate($data) ? json_decode($data, true)['action'] : '';
        $handlers = array_merge($this->getEventActionHandlers('finish', $protocolAction), $this->getEventActionHandlers('finish', '*'));
        foreach ($handlers as $protocol => $handler) {
            $this?->logger?->debug("Handling finish for action '$protocolAction' [{$protocol}] with data: {$data}");
            $handler($server, $taskId, $data);
        }
    }

    // PIPE MESSAGE HANDLERS ->
    public function registerPipeMessageHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->pipeMessageHandlers[$protocolAction]) || !($this->pipeMessageHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->pipeMessageHandlers[$protocolAction] = new CallableCollection();
        }
        $this->pipeMessageHandlers[$protocolAction]->offsetSet($protocol, $callback);
        $this->registerEventHandlers('pipeMessage');
    }

    public function getPipeMessageHandlers(string $protocolAction): ?array
    {
        return $this->getEventActionHandlers('pipeMessage', $protocolAction); //$this->pipeMessageHandlers[$protocolAction]?->toArray();
    }

    public function hasPipeMessageHandlers(string $protocolAction): bool
    {
        return isset($this->pipeMessageHandlers[$protocolAction]) && $this->pipeMessageHandlers[$protocolAction]->count() > 0;
    }

    public function removePipeMessageHandlers(string $protocolAction): void
    {
        $this->pipeMessageHandlers[$protocolAction]?->clear();
        unset($this->pipeMessageHandlers[$protocolAction]);
        $this->registerEventHandlers('pipeMessage');
    }

    public function handlePipeMessageEvent(TcpUdpServer $server, int $srcWorkerId, string $data): void
    {
        $this?->logger?->debug("Handling pipe message event for worker {$srcWorkerId} with data: {$data}");
        $protocolAction = json_validate($data) ? json_decode($data, true)['action'] : '';
        $handlers = array_merge($this->getEventActionHandlers('pipeMessage', $protocolAction), $this->getEventActionHandlers('pipeMessage', '*'));

        foreach ($handlers as $protocol => $handler) {
            $this?->logger?->debug("Handling pipe message for action '$protocolAction' [{$protocol}] with data: {$data}");
            $handler($server, $srcWorkerId, $data);
        }
    }

    // CLOSE HANDLERS ->

    public function handleCloseEvent(TcpUdpServer $server, int $fd, int $reactorId): void
    {
        $this?->logger?->debug("Handling close event for worker {$server->worker_id} with fd: {$fd} and reactorId: {$reactorId}");
        if (isset($this->protocolManagers) && $this->protocolManagers->count() > 0) {
            foreach ($this->protocolManagers as $protocolManager) {
                $this?->logger?->debug("Checking protocol manager {$protocolManager->protocol->name} for connect event");
                $protocolManager->runOnCloseConnection($server, $fd, $reactorId);
            }
        }
        $handlers = $this->getEventActionHandlers('close', null);
        foreach ($handlers as $protocol => $handler) {
            $this?->logger?->debug("Handling close for action[{$protocol}] with fd: {$fd} and reactorId: {$reactorId}");
            $handler($server, $fd, $reactorId);
        }
    }

    public function registerCloseHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        if (!isset($this->closeHandlers[$protocolAction]) || !($this->closeHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->closeHandlers[$protocolAction] = new CallableCollection();
        }
        $this->closeHandlers[$protocolAction]->offsetSet($protocol, $callback);
        $this->registerEventHandlers('close');
    }

    public function getCloseHandlers(string $protocolAction): ?array
    {
        return $this->getEventActionHandlers('close', $protocolAction); //$this->closeHandlers[$protocolAction]?->toArray();
    }

    public function hasCloseHandlers(string $protocolAction): bool
    {
        return isset($this->closeHandlers[$protocolAction]) && $this->closeHandlers[$protocolAction]->count() > 0;
    }

    public function removeCloseHandlers(string $protocolAction): void
    {
        $this->closeHandlers[$protocolAction]?->clear();
        unset($this->closeHandlers[$protocolAction]);
        $this->registerEventHandlers('close');
    }
}