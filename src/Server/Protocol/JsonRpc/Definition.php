<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\JsonRpc;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Action;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Base;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Rpc\MethodDescriptor;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Rpc\MethodsCollection;

class Definition extends Action
{
    protected(set) string $call = 'jsonrpc';
    private array $registeredMethods = [];
    private MethodsCollection $methods;

    /**
     * Adds a new delivery type to the registered delivery types.
     * Used by add responses for methods that require a specific delivery type.
     * @override 1.0.0
     * @param string $action The action associated with the delivery type.
     * @param string $delivery The delivery type to be registered.
     *
     * @return void
     */
    public function addDeliveryType(string $action, string $delivery): void
    {
        if (in_array($action, $this->registeredMethods)) {
            trigger_error("The action $action is already registered.", E_USER_WARNING);
            return;
        }
        $this->deliveryTypes->offsetSet($action, $delivery);// for method delivery types
        parent::addDeliveryType($action, $delivery); // for protocol delivery types
    }

    public function addMethod(MethodDescriptor $method, ?string $deliveryType = null): void
    {
        if (!isset($this->methods)) {
            $this->methods = new MethodsCollection();
        }
        $this->methods->offsetSet($method->method, $method);
        $this->registeredMethods[] = $method->method;
        if ($deliveryType) {
            if (class_exists($deliveryType) && is_subclass_of($deliveryType, Base::class)) {
                $this->addDeliveryType($method->method, $deliveryType);
            } else {
                trigger_error("The specified delivery type $deliveryType is not valid for method " . $method->method, E_USER_WARNING);
            }
        }
    }
    public function addMethods(MethodDescriptor ...$methods): void
    {
        foreach ($methods as $method) {
            $this->addMethod($method);
        }
    }

    public function hasMethod(string $method): bool
    {
        return $this->methods->offsetExists($method);
    }

    public function getMethod(string $method): ?MethodDescriptor
    {
        return $this->methods->offsetGet($method);
    }
    public function getMethods(): MethodsCollection
    {
        return $this->methods;
    }
}