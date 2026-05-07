<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\ResponseCollection;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;
use Tabula17\Satelles\Utilis\Exception\UnexpectedValueException;

/**
 * Represents an action descriptor class that handles resolution of protocol handlers or statuses
 * based on provided input data and context. Each action corresponds to a specific handler
 * or processing logic and can be dynamically resolved or overridden by resolvers.
 */
class Action extends AbstractDescriptor
{
    protected RequestCollection $actionsResolvers;
    protected ResponseCollection $responsesTypes;

    protected ResponseCollection $deliveryTypes;

    /**
     * @throws UnexpectedValueException
     */
    public function __construct(array $properties = [], ?RequestCollection $actionsResolvers = null, ?ResponseCollection $responsesTypes = null, ?ResponseCollection $deliveryTypes = null)
    {
        parent::__construct($properties);
        // Check if all property resolvers are defined in the protocol
        if ($actionsResolvers) {
            foreach ($actionsResolvers as $property => $resolver) {
                if (!$this->offsetExists($property)) {
                    $actionsResolvers->offsetUnset($property);
                    trigger_error("Resolver for property '{$property}' cannot be set. No such action defined in protocol.", E_USER_WARNING);
                }
            }
        }
        $this->actionsResolvers = $actionsResolvers ?? new RequestCollection();
        if ($responsesTypes) {
            foreach ($responsesTypes as $property => $response) {
                if (!$this->offsetExists($property)) {
                    $responsesTypes->offsetUnset($property);
                    trigger_error("Response type for property '{$property}' cannot be set. No such action defined in protocol.", E_USER_WARNING);
                }
            }
        }
        $this->responsesTypes = $responsesTypes ?? new ResponseCollection();
        if ($deliveryTypes) {
            foreach ($deliveryTypes as $property => $delivery) {
                if (!$this->offsetExists($property)) {
                    $deliveryTypes->offsetUnset($property);
                    trigger_error("Response type for property '{$property}' cannot be set. No such action defined in protocol.", E_USER_WARNING);
                }
            }
        }
        $this->deliveryTypes = $deliveryTypes ?? new ResponseCollection();
    }

    protected function getProperty(mixed $value): string|int|false
    {
        return array_search($value, $this->toArray(), true);
    }

    public function addActionResolver(string $action, string $resolver): void
    {
        $property = $this->getProperty($action);
        if ($property && is_string($property)) {
            $this->actionsResolvers->offsetSet($property, $resolver);
        }
    }

    public function hasActionResolver(string $action): bool
    {
        $property = $this->getProperty($action);
        return $property && $this->actionsResolvers->offsetExists($property);
    }

    public function addResponseType(string $action, string $response): void
    {
        $property = $this->getProperty($action);
        if ($property && is_string($property)) {
            $this->responsesTypes->offsetSet($property, $response);
        }
    }

    public function hasResponseType(string $action): bool
    {
        $property = $this->getProperty($action);
        return $property && $this->responsesTypes->offsetExists($property);
    }

    public function validateMessage(string|array|null $message): bool
    {
        if (!$message) {
            return false;
        }
        if (is_string($message)) {
            $message = json_decode($message, true);
        }
        if (isset($message['action']) && in_array($message['action'], $this->toArray())) {
            $property = $this->getProperty($message['action']);
            $resolverClass = $this->actionsResolvers->offsetGet($property);
            if ($resolverClass) {
                return $resolverClass::validatePayload($message['payload'] ?? []);
            }
            trigger_error("Action '{$message['action']}' has no resolver defined. Unable to validate payload.", E_USER_WARNING);
            return false;
        }
        trigger_error("Message cannot be validated/decoded. No action found.", E_USER_WARNING);
        return false;
    }

    public function resolve(string $action, ...$args): ?Payload
    {
        $class = $this->actionsResolvers->offsetGet($this->getProperty($action));
        // var_dump( $this->actionsResolvers->toArray());
        if ($class) {
            return new $class(...$args);
        }
        return null;
    }

    public static function getProtocolName(): ?string
    {
        return static::PROTOCOL?->shortName();
    }

    public function getResponseType(string $action): ?string
    {
        return $this->responsesTypes->offsetGet($this->getProperty($action));
    }

    public function getDeliveryType(string $action): ?string
    {
        return $this->deliveryTypes->offsetGet($this->getProperty($action));
    }

    public function hasDeliveryType(string $action): bool
    {
        return $this->deliveryTypes->offsetExists($this->getProperty($action));
    }

    public function addDeliveryType(string $action, string $delivery): void
    {
        $property = $this->getProperty($action);
        if ($property && is_string($property)) {
            $this->deliveryTypes->offsetSet($property, $delivery);
        }
    }

    /**
     * Generates and returns an array of payload models, where each key corresponds to
     * an action and contains details about the request, delivery (if applicable), and response models.
     *
     * @return array An associative array where each key maps to a payload model containing 'request',
     *               'delivery' (if available), and 'response' data.
     */
    public function getPayloadModels(): array
    {
        $models = [];
        foreach ($this->toArray() as $key => $action) {
            $models[$key] = [
                'request' => $this->actionsResolvers->offsetGet($key)::getModel(),
                //'delivery' => null,
                'response' => $this->responsesTypes->offsetGet($key)::getModel()
            ];
            if ($this->deliveryTypes->offsetGet($key) !== null) {
                $models[$key]['delivery'] = $this->deliveryTypes->offsetGet($key)::getModel();
            }
        }
        return $models;
    }
}