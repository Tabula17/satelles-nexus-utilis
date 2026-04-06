<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol;


use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Action;

interface ProtocolManagerInterface
{
    const ServiceProtocol protocol = ServiceProtocol::UNKNOWN;
    public Action $request {
        get;
    }
/*    public Type $responses {
        get;
    }*/
    /**
     * Initializes necessary configurations or operations before the server starts.
     * Event launched by MatrixTrait enabled servers.
     *
     * @param HamumServerInterface $server The server instance to be initialized on start.
     * @return void
     */
    public function initializeOnStart(HamumServerInterface $server): void;

    /**
     * Initializes resources or performs setup tasks on specific worker processes.
     * Event launched by MatrixTrait enabled servers.
     *
     * @param HamumServerInterface $server The server instance to be initialized on the worker.
     * @param int $workerId The unique identifier of the worker on which initialization takes place.
     * @return void
     */
    public function initializeOnWorkers(HamumServerInterface $server, int $workerId): void;

    /**
     * Executes operations or handles tasks when a new connection is opened.
     *
     * @param mixed ...$args The arguments associated with the open connection, their nature and structure depend on the context of implementation.
     * @return void
     */
    public function runOnOpenConnection(...$args): void; // HamumServerInterface $server, int $fd, int $reactorId but WS -> HamumServerInterface $server, Definition $request

    /**
     * Executes logic to handle actions when a connection is closed.
     *
     * @param HamumServerInterface $server The server instance handling the connection.
     * @param int $fd The file descriptor of the closed connection.
     * @param int $reactorId The reactor thread ID where the connection was closed.
     * @return void
     */
    public function runOnCloseConnection(HamumServerInterface $server, int $fd, int $reactorId): void;

    /**
     * Cleans up resources associated with a connection or the server.
     * This method is launched before workerStop and shutdown events.
     *
     * @param HamumServerInterface $server The server instance managing the resources.
     * @param int $fd The file descriptor of the connection to clean up. Defaults to 0 for general cleanup.
     * @return void
     */
    public function cleanUpResources(HamumServerInterface $server, int $fd = 0): void;

    /**
     * Registers protocol handlers for the given server before it starts.
     * Event launched by MatrixTrait enabled servers.
     * In this method, you can register handlers for specific protocols or actions and set up event listeners.
     *
     * @param HamumServerInterface $server The server instance to register protocol handlers with.
     * @return void
     */
    public function registerProtocolHandlers(HamumServerInterface $server): void;

}