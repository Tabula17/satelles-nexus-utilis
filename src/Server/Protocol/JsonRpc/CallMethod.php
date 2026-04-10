<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\JsonRpc;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data\MethodDescriptor;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\JsonRpc\ResponseDescriptor\JsonRpcResponse;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\JsonRpc\ResponseDescriptor\ResultResponse;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Payload;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;
use Throwable;

class CallMethod extends Payload
{
//MethodDescriptor
    public MethodDescriptor $payload {
        get {
            return $this->payload;
        }
        set(array|MethodDescriptor $payload) {
            if (is_array($payload)) {
                $payload = new MethodDescriptor($payload);
            }
            $this->payload = $payload;

        }
    }
    public Definition $protocol {
        get {
            return $this->protocol;
        }
    }
    protected null|string $idProperty {
        get {
            return 'id';
        }
    }
    protected(set) string $id;
    private mixed $result;
    private bool $datasetInResponse = true;

    public function initialize(?array &$values): void
    {
        /**
         *  in values:
         *  - id -> if not set, we datasetInResponse = false and no response to client
         *  - method -> with this we can find the method to call from protocol
         *  - params -> after method descriptor instantiation, we can set the params values and validate before calling the method
         *  - jsonrpc
         */
        if (!static::validatePayload($values)) {
            $this->status = Status::error;
            $this->result = JsonRpcResponse::errorFromCode(32600);
            return;
        }
        if (!$this->protocol->hasMethod($values['method'])) {
            $this->status = Status::error;
            $this->result = JsonRpcResponse::errorFromCode(32601);
            return;
        }
        $this->datasetInResponse = isset($values['id']);
        if (!$this->getResponseID()) {
            $this->id = $values['id'] ?? uniqid('call:', false);
        }

        $payload = $this->protocol->getMethod($values['method']);
        $requiredParams = $payload->parameters->collectRequired('name');
        if (count($requiredParams) > 0) {
            if (!isset($values['params']) || count(array_diff($requiredParams, array_keys($values['params'] ?? []))) > 0) {
                $this->status = Status::error;
                $this->result = JsonRpcResponse::errorFromCode(32602);
                return;
            }
        }
        foreach ($values['params'] ?? [] as $name => $value) {
            if ($payload->parameters->offsetExists($name)) {
                $payload->parameters->setParamValue($name, $value);
            }
        }
        $values['payload'] = $payload->parameters->toArray();
    }

    public function datasetInResponse(): bool
    {
        return $this->datasetInResponse;
    }

    public function handle(...$args): static
    {
        // Execute payload->handler and store result in $this->result
        if (isset($this->result) && $this->result instanceof JsonRpcResponse) {
            return $this;
        }
        [$server, $fd] = $args;
        foreach ($this->payload->parameters->getInjected() as $parameter) {
            if ($parameter->name === 'server') {
                $parameter->value = $server;
            }
            if ($parameter->name === 'fd') {
                $parameter->value = $fd;
            }
        }
        try {
            $this->result = $this->payload->handler->call($this->payload->handler, $this->payload->parameters->toArray());
            $eval = $this->result;
            if ($eval instanceof AbstractDescriptor) {
                $eval = $eval->toArray();
            }
            $this->status = Status::fromString($eval['status'] ?? $eval['success'] ?? (!empty($eval) ? 'ok' : 'error'));

        } catch (Throwable $exception) {
            $this->status = Status::error;
            $this->result = [
                'code' => $exception->getCode() ?? 32603,
                'message' => $exception->getMessage(),
            ];
        }
        return $this;
    }

    public function getResponse(...$args): ?ResultResponse
    {
        // If datasetInResponse() is true, return a Response object. Response class from protocol->deliveryType
        /* if ($this->status === Status::error) {
             return $this->result;
         }*/
        if ($this->datasetInResponse()) {
            $class = $this->protocol->getDeliveryType($this->payload->method) ?? $this->protocol->getResponseType($this->action);
            $response = $class ? new $class($this->status, $this->result) : null;
            if (!$response instanceof ResultResponse) {
                return new ResultResponse($this->status, ['response' => $this->result, 'id' => $this->_id]);
            }

            return $response->setRequestId($this->getID())->forceId($this->getResponseID());
        }
        return null;
    }

    public function getResult(...$args): mixed
    {
        return $this->result;
    }

    public static function validatePayload(array $data): bool
    {
        return isset($data['method'], $data['jsonrpc']) && $data['jsonrpc'] === '2.0';
    }
}