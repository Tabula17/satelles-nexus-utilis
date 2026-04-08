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
class ErrorResponse extends Base
{
    protected null|string $idProperty {
        get {
            return 'id';
        }

    }
    public JsonRpcErrorDescriptor $response {
        get {
            return $this->response;
        }
    }
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

    public function initialize(Status $status, ?array &$values): void
    {
        $response = [];
        if (isset($values['response']) && $values['response'] instanceof JsonRpcErrorDescriptor) {
            $response = $values['response']->toArray();
        }
        $code = $response['error']['code'] ?? $values['code'] ?? 0;
        $message = $response['error']['message'] ?? $values['message'] ?? $values['error'] ?? 'Unknow error';
        $response = JsonRpcErrorDescriptor::fromCode($code, $message);
        if (isset($values['id'])) {
            $response->set('id', $values['id']);
        }
        $values['response'] = $response;

    }
    public function forceId(int|string $id): static
    {
        $this->id = $id;
        if(isset($this->response)) {
            $this->response->set('id', $id);
        }
        return $this;
    }
}