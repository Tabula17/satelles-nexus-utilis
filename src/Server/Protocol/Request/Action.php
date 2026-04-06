<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\ResponseCollection;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

/**
 * Represents an action descriptor class that handles resolution of protocol handlers or statuses
 * based on provided input data and context. Each action corresponds to a specific handler
 * or processing logic and can be dynamically resolved or overridden by resolvers.
 */
class Action extends AbstractDescriptor
{
    private RequestCollection $actionsResolvers;
    private ResponseCollection $responsesTypes;

    /**
     * @throws \Tabula17\Satelles\Utilis\Exception\UnexpectedValueException
     */
    public function __construct(?array $properties = null, ?RequestCollection $actionsResolvers = null, ?ResponseCollection $responsesTypes = null)
    {
        parent::__construct($properties);
        // Check if all property resolvers are defined in the protocol
        foreach ($actionsResolvers ?? [] as $property => $resolver) {
            if (!$this->offsetExists($property)) {
                $this->actionsResolvers->offsetUnset($property);
                trigger_error("Resolver for property '{$property}' cannot be set. No such action defined in protocol.", E_USER_WARNING);
            }
        }
        $this->actionsResolvers = $actionsResolvers ?? new RequestCollection();

        foreach ($responsesTypes ?? [] as $property => $response) {
            if (!$this->offsetExists($property)) {
                $this->responsesTypes->offsetUnset($property);
                trigger_error("Response type for property '{$property}' cannot be set. No such action defined in protocol.", E_USER_WARNING);
            }
        }
        $this->responsesTypes = $responsesTypes ?? new ResponseCollection();
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
        if(is_string($message)) {
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
    /*
    private array $resolvers {
        set(array $resolvers) {
            //array_search($resolver, $this->toArray(), true)
            $resolvers = array_filter($resolvers, fn($resolver) => $this->offsetExists($this->getKeyFromValue($resolver)), ARRAY_FILTER_USE_KEY);
            $this->resolvers = $resolvers;
        }
    }

    //private function
    public function addResolver(string $name, string|callable $resolver): void
    {
        $resolvers = $this->resolvers ?? [];
        $resolvers[$name] = $resolver;
        $this->resolvers = $resolvers;
    }

    public function hasResolver(string $name): bool
    {
        return isset($this->resolvers[$name]);
    }

    public function getResolver(string $name): string|callable|null
    {
        return $this->resolvers[$name] ?? null;
    }

    public function getResolvers(): array
    {
        return $this->resolvers;
    }

    public function getKeyFromValue(string $value): string
    {
        return array_search($value, $this->toArray(), true);
    }
    public function getProtocolFor(array $data, ?int $fd, ?HamumServerInterface $server = null, ?ProtocolManagerInterface $protocolManager = null): RequestHandlerInterface|Status
    {
        if (isset($data['action']) && in_array($data['action'], $this->toArray())) {
            $resolver = $data['action'];//$this->getKeyFromValue($data['action']);//array_search($data['action'], $this->toArray(), true);
            $class = Generic::class;

            if (isset($this->resolvers[$resolver])) {
                if (is_callable($this->resolvers[$resolver])) { // if callable, execute and check return type. pass arguments as handler expects
                    $result = $this->resolvers[$resolver]($fd ?? 0, $data, $server, $protocolManager);
                    if ($result instanceof RequestHandlerInterface || $result instanceof Status) {
                        return $result;
                    }
                    throw new UnexpectedValueException('Resolver for ' . $data['action'] . ' must return an instance of ' . RequestHandlerInterface::class . ' or ' . Status::class);
                }
                if (is_string($this->resolvers[$resolver]) && class_exists($this->resolvers[$resolver]) && is_a($this->resolvers[$resolver], RequestHandlerInterface::class, true)) {
                    //return new $this->resolvers[$resolver]($data);
                    $class = $this->resolvers[$resolver];
                }
                trigger_error("Action '{$data['action']} ({$resolver})' has resolver but it's not a valid class or callable. Using default class '{$class}'", E_USER_WARNING);
            } else {
                $className = $this->getNamespace() . '\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $data['action'])));
                if (class_exists($className) && is_a($className, RequestHandlerInterface::class, true)) {
                    // return new $className($data);
                    $class = $className;
                } else {
                    $resolvers = implode(', ', array_keys($this->resolvers));
                    trigger_error("Action '{$data['action']} ({$resolver} -> [{$resolvers}])' has no resolver  or Custom class defined ('{$className}'). Using default class '{$class}'", E_USER_WARNING);
                }
            }
            return new $class($data);
        }
        trigger_error('No action for protocol ' . static::PROTOCOL->shortName() . ' -> {' . ($data['action'] ?? 'noType') . '} detected. Must be one of: ' . implode(', ', $this->toArray()), E_USER_WARNING);
        return Status::undefined;
    }

    private function getNamespace(): string
    {
        $fullClassName = get_class($this);
        // Find the last backslash position
        $lastBackslashPos = strrpos($fullClassName, '\\');
        if ($lastBackslashPos === false) {
            // No namespace (global namespace)
            return '';
        }

        // Extract the substring before the last backslash
        return substr($fullClassName, 0, $lastBackslashPos);
    }
*/
    public static function getProtocolName(): ?string
    {
        return static::PROTOCOL->shortName();
    }

    public function getResponseType(string $action): ?string
    {
        return $this->responsesTypes->offsetGet($this->getProperty($action));
    }
}