<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\JsonRpc;

use Psr\Log\LoggerInterface;
use Swoole\Table;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data\MethodsCollection;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\JsonRpc\ResponseDescriptor\ErrorResponse;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ServiceProtocol;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;

class JsonRpcManager implements ProtocolManagerInterface
{

    const ServiceProtocol protocol = ServiceProtocol::JSONRPC;

    public Definition $request {
        get {
            return $this->request;
        }
    }
    private Table $rpcRequests;

    public function __construct(
        ?Definition                       $request,
        private readonly ?LoggerInterface $logger = null
    )
    {
        $this->request = $request ?? new Definition([
            'call' => 'jsonrpc'
        ]);
    }

    /**
     * @inheritDoc
     */
    public function initializeOnStart(HamumServerInterface $server): void
    {
        $this->rpcRequests = new Table(2048);
        //Request Table
        $this->rpcRequests->column('requestId', Table::TYPE_STRING, 32);
        $this->rpcRequests->column('fd', Table::TYPE_INT);
        $this->rpcRequests->column('method', Table::TYPE_STRING, 128);
        $this->rpcRequests->column('params', Table::TYPE_STRING, 4096); // JSON
        $this->rpcRequests->column('createdAt', Table::TYPE_INT);
        $this->rpcRequests->column('status', Table::TYPE_STRING, 20); // pending, processing, completed, failed
        $this->rpcRequests->column('coroutineId', Table::TYPE_INT);
        $this->rpcRequests->create();
    }

    /**
     * @inheritDoc
     */
    public function initializeOnWorkers(HamumServerInterface $server, int $workerId): void
    {
        // TODO: Implement initializeOnWorkers() method.
    }

    /**
     * @inheritDoc
     */
    public function runOnOpenConnection(...$args): void
    {
        // TODO: Implement runOnOpenConnection() method.
    }

    /**
     * @inheritDoc
     */
    public function runOnCloseConnection(HamumServerInterface $server, int $fd, int $reactorId): void
    {
        // TODO: Implement runOnCloseConnection() method.
    }

    /**
     * @inheritDoc
     */
    public function cleanUpResources(HamumServerInterface $server, int $fd = 0): void
    {
        // TODO: Implement cleanUpResources() method.
    }

    /**
     * @inheritDoc
     */
    public function registerProtocolHandlers(HamumServerInterface $server): void
    {
        if($server::TYPE->isWebsockets()){
            $server->registerMessageHandlers($this->request->call, $this->handleCalls(...), static::protocol->shortName());
        }
        if($server::TYPE->isHttp()){
            $server->registerRequestHandlers($this->request->call, $this->handleCalls(...), static::protocol->shortName());
        }
    }

    public function handleCalls(HamumServerInterface $server, int $fd, array $data = []): void
    {

        if (!$this->request->hasMethod($data['method'])) {
            $error = new ErrorResponse(
                Status::error,
                [
                    'code' => -32601,
                    'message' => 'Method not found'
                ]
            );
            if (isset($data['id'])) {
                $error->forceId($data['id']);
            }
        }else{

            $method = $this->request->getMethod($data['method']);
            //if not request ID don't send response
            $requestId = $data['id'] ?? null;

        }

    }
}