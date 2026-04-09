<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\JsonRpc;

use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Http\Response;
use Swoole\Http\Request;
use Swoole\Table;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\JsonRpc\ResponseDescriptor\ResultResponse;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ServiceProtocol;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Base;
use Tabula17\Satelles\Utilis\Exception\UnexpectedValueException;

class JsonRpcManager implements ProtocolManagerInterface
{

    const ServiceProtocol protocol = ServiceProtocol::JSONRPC;

    public Definition $request {
        get {
            return $this->request;
        }
    }
    private Table $rpcRequests;
    private int $tickId;

    /**
     * @throws UnexpectedValueException
     */
    public function __construct(
        ?Definition                       $request,
        private readonly int              $rpcTimeOut = 600,
        private readonly ?LoggerInterface $logger = null
    )
    {
        $this->request = $request ?? new Definition([
            'call' => 'jsonrpc'
        ]);
        if (!$this->request->hasActionResolver($this->request->call)) {
            $this->request->addActionResolver($this->request->call, CallMethod::class);
        }
        if (!$this->request->hasResponseType($this->request->call)) {
            $this->request->addResponseType($this->request->call, ResultResponse::class);
        }
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
        $this->rpcRequests->column('finalizedAt', Table::TYPE_INT);
        $this->rpcRequests->column('status', Table::TYPE_STRING, 20); // pending, processing, completed, failed
        $this->rpcRequests->column('coroutineId', Table::TYPE_INT);
        $this->rpcRequests->column('executeTimeout', Table::TYPE_INT);
        $this->rpcRequests->create();
        if ($server->isCronosEnabled()) {
            $this->tickId = $server->addTick($this->rpcTimeOut, fn() => $this->cleanUpRpcRequests());
        }
    }

    private function cleanUpRpcRequests(): void
    {
        $now = time();
        $limit = $now - $this->rpcTimeOut;
        foreach ($this->rpcRequests as $requestId => $request) {
            $execTimeout = $now - ($request['executeTimeout'] ?? $this->rpcTimeOut);
            if ($request['status'] === Status::pending->value) {
                if ($request['createdAt'] >= $execTimeout) {
                    $this->cancelRpcRequest($requestId);
                }
            } elseif ($request['createdAt'] >= $limit) {
                $this->cancelRpcRequest($requestId);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function initializeOnWorkers(HamumServerInterface $server, int $workerId): void
    {
        // TODO: Implement initializeOnWorkers() method.

        /**
         * @var RpcProcessorInterface $processor
         * foreach ($this->rpcProcessors as $processor) {
         * $processor->initializeOnWorkers($server, $workerId);
         * }
         */
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
        if (getmypid() === $server->getMasterPid()) {
            if ($server->isCronosEnabled()) {
                $server->removeTick($this->tickId);
            }
            $this->rpcRequests->destroy();
        }
    }

    /**
     * @inheritDoc
     */
    public function registerProtocolHandlers(HamumServerInterface $server): void
    {
        if ($server::TYPE->isWebsocket()) {
            $server->registerMessageHandlers($this->request->call, $this->handleCalls(...), static::protocol->shortName());
        }
        if ($server::TYPE->isHttp()) {
            $server->registerRequestHandlers($this->request->call, $this->handleRequests(...), static::protocol->shortName());
        }
    }

    public function handleCalls(HamumServerInterface $server, int $fd, array $data = []): void
    {
        //$resolver = $this->request->resolve($this->request->call, null, $data, $this->request)?->handle($server, $fd);
        $data = $data['payload'] ?? $data;
        if (!$this->request->hasMethod($data['method'])) {
            $error = new ResultResponse(
                Status::error,
                [
                    'code' => -32601,
                    'message' => 'Method not found'
                ]
            );
            if (isset($data['id'])) {
                $error->forceId($data['id']);
            }
            $server->send($fd, json_encode($error->jsonSerialize()));
        } else {

            $resolver = $this->request->resolve($this->request->call, $this->request->getMethod($data['method'])->handler, $data, $this->request);//?->handle($server, $fd);
            $this->rpcRequests->set($resolver->getID(), [
                'requestId' => $resolver->getID(),
                'fd' => $fd,
                'method' => $data['method'],
                'params' => json_encode($data['params'] ?? []),
                'createdAt' => time(),
                'status' => Status::accepted->value,
                'executeTimeout' => $data['timeout'] ?? null,
                'coroutineId' => 0
            ]);
            //$resolver->handle($server, $fd);
            $args = [$server, $fd, $resolver->getID()];
            if ($resolver->datasetInResponse()) {
                $args[] = true;
            }
            $coroutineId = Coroutine::create($this->executeCall(...), $resolver->handle(...), ...$args);
            /*
            if ($resolver->datasetInResponse()) {
                $coroutineId = Coroutine::create($this->executeCall(...), $resolver->handle(...), $server, $fd, $resolver->getID());

            } else {
                $coroutineId = Coroutine::create($this->executeCall(...), $resolver->handle(...), $server, $fd, $resolver->getID(), false);
            }*/
            $this->updateRpcRequest($resolver->getID(), 'coroutineId', $coroutineId);
            $this->updateRpcRequest($resolver->getID(), 'status', Status::pending->value);

        }

    }

    private function executeCall(callable $handler, HamumServerInterface $server, int $fd, string|int $requestId, bool $reply = true): void
    {
        /**
         * @var Base $response
         */
        $response = $handler($server, $fd)->getResponse();
        $this->updateRpcRequest($requestId, 'coroutineId', null);
        $this->updateRpcRequest($requestId, 'finalizedAt', time());
        $this->updateRpcRequest($requestId, 'status', $response->status->value);
        if ($reply) {
            $server->send($fd, json_encode($response->jsonSerialize()));
        }
    }

    public function handleRequests(Request $request, Response $response): void
    {
        $data = $request->post;
        $fd = $request->fd;

        if (!$this->request->hasMethod($data['method'])) {
            $error = new ResultResponse(
                Status::error,
                [
                    'code' => -32601,
                    'message' => 'Method not found'
                ]
            );
            if (isset($data['id'])) {
                $error->forceId($data['id']);
            }
            $response->header('Content-Type', 'application/json');
            $response->end(json_encode($error->response->jsonSerialize()));
        } else {
            $resolver = $this->request->resolve($this->request->call, $this->request->getMethod($data['method'])->handler, $data, $this->request);//?->handle($server, $fd);
            $this->rpcRequests->set($resolver->getID(), [
                'requestId' => $resolver->getID(),
                'fd' => $fd,
                'method' => $data['method'],
                'params' => json_encode($data['params'] ?? []),
                'createdAt' => time(),
                'status' => Status::accepted->value,
                'executeTimeout' => $data['timeout'] ?? null,
                'coroutineId' => 0
            ]);
            $args = [$response, $fd, $resolver->getID()];
            if ($resolver->datasetInResponse()) {
                $args[] = true;
            }
            $coroutineId = Coroutine::create($this->executeCallRequest(...), $resolver->handle(...), ...$args);
            $this->updateRpcRequest($resolver->getID(), 'coroutineId', $coroutineId);
            $this->updateRpcRequest($resolver->getID(), 'status', Status::pending->value);

        }
    }

    private function executeCallRequest(callable $handler, Response $httpResponse, int $fd, string|int $requestId, bool $reply = true): void
    {
        /**
         * @var Base $response
         */
        $response = $handler($httpResponse, $fd)->getResponse();
        $this->updateRpcRequest($requestId, 'coroutineId', null);
        $this->updateRpcRequest($requestId, 'finalizedAt', time());
        $this->updateRpcRequest($requestId, 'status', $response->status->value);
        if ($reply) {
            $httpResponse->header('Content-Type', 'application/json');
            $httpResponse->end(json_encode($response->response->jsonSerialize()));
        }

    }

    private function updateRpcRequest(string $requestId, string $column, mixed $value): void
    {
        if ($this->rpcRequests->get($requestId)) {
            $this->rpcRequests->set($requestId, array_merge($this->rpcRequests->get($requestId), [$column => $value]));
        }
    }


    private function cancelRpcRequest(string $requestId): void
    {
        $request = $this->rpcRequests->get($requestId);
        if ($request && $request['coroutineId'] > 0 && Coroutine::exists($request['coroutineId'])) {
            Coroutine::cancel($request['coroutineId']);
        }
        $this->rpcRequests->del($requestId);
    }
}