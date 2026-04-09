<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\JsonRpc\ResponseDescriptor;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Base;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

/**
 * Represents an error response that extends the functionality of the Base class.
 * Provides access to specific properties and a method for initialization.
 *
 * Error Code           Message             Description
 * −32700               Parse error         Invalid JSON was received
 * −32600               Invalid Request     The JSON sent is not a valid Request object
 * −32601               Method not found    The method does not exist / is not available
 * −32602               Invalid params      Invalid method parameter(s)
 * −32603               Internal error      Internal JSON-RPC error
 * −32000 to −32099     Server error        Reserved for implementation-defined server errors
 */
class ResultResponse extends Base
{
    protected null|string $idProperty {
        get {
            return 'id';
        }

    }
    public JsonRpcResponse $response;
    protected(set) int|string|null $id {
        get {
            return $this->id;
        }
        set(int|string|null $id) {
            $this->id = $id;
            if (isset($this->response) && !$this->response->offsetExists('id')) {
                $this->response->set('id', $id);
            }
        }
    }
    private string|int $_requestId;
    public function initialize(Status $status, ?array &$values): void
    {

        if($status->isError()) {
            $response = [];
            if (isset($values['response']) && $values['response'] instanceof AbstractDescriptor) {
                $response = $values['response']->toArray();
            }
            //find error code
            $code = $response['error']['code'] ?? $values['code'] ?? 0;
            //find error message
            $message = $response['error']['message'] ?? $values['message'] ?? $values['error'] ?? 'Unknow error';
            //create error response
            $response = JsonRpcResponse::errorFromCode($code, $message);
            //set id if exists
            if (isset($values['id'])) {
                $response->set('id', $values['id']);
            }
            $values['response'] = $response;
        }else{
            $result = $values['response'] ?? $values; // if result is not set, use the whole values
            $values['response'] = new JsonRpcResponse(['result' => $result, 'id' => $values['id'] ?? null]);
        }

    }
    public function forceId(int|string $id): static
    {
        $this->id = $id;
        if(isset($this->response)) {
            $this->response->set('id', $id);
        }
        return $this;
    }

    public function getRequestId(): string|int
    {
        return $this->_requestId ?? $this->id;
    }
    public function setRequestId(string|int $requestId): static
    {
        $this->_requestId = $requestId;
        return $this;
    }
}