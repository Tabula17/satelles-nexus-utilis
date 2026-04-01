<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response;

use Tabula17\Satelles\Nexus\Utilis\Exception\UnexpectedValueException;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ServiceProtocol;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class Type extends AbstractDescriptor
{
    const ServiceProtocol PROTOCOL = ServiceProtocol::GENERIC;

    protected(set) string $message = 'message';
    protected(set) string $error = 'error';
    protected(set) string $success = 'success';
    private array $resolvers = [
        'message' => Message::class,
        'error' => Error::class,
        'success' => Success::class
    ]
        {
            set(array $resolvers) {
                $resolvers = array_filter($resolvers, fn($resolver) => $this->offsetExists($this->getKeyFromValue($resolver)), ARRAY_FILTER_USE_KEY);
                $this->resolvers = $resolvers;
            }
        }

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

    /**
     * @throws UnexpectedValueException
     */
    public function getProtocolFor(array $data): ResponseInterface
    {
        if (isset($data['type']) && in_array($data['type'], $this->toArray())) {
            $class = Base::class;
            $resolver = $data['type'];//$this->getKeyFromValue($data['type']);//array_search($data['type'], $this->toArray(), true);
            /*if (isset($this->resolvers[$resolver])) {
                if (is_callable($this->resolvers[$resolver])) {
                    return $this->resolvers[$resolver]($data);
                }
                if (is_string($this->resolvers[$resolver]) && class_exists($this->resolvers[$resolver])) {
                    return new $this->resolvers[$resolver]($data);
                }
            }
            $className = $this->getNamespace() . '\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', str_replace('_response', '', $data['type']))));
            if (class_exists($className)) {
                return new $className($data);
            }
            $resolvers = implode(', ', array_keys($this->resolvers));
            trigger_error("Type '{$data['type']} ({$resolver} -> [{$resolvers}])' has no resolver  or Custom class defined ('{$className}'). Using default class '{$class}'", E_USER_WARNING);*/

            if (isset($this->resolvers[$resolver])) {
                if (is_callable($this->resolvers[$resolver])) { // if callable, execute and check return type. pass arguments as handler expects
                    $result = $this->resolvers[$resolver]($data);
                    if ($result instanceof ResponseInterface || $result instanceof Status) {
                        return $result;
                    }
                    throw new UnexpectedValueException('Resolver for ' . $data['action'] . ' must return an instance of ' . ResponseInterface::class . ' or ' . Status::class);
                }
                if (is_string($this->resolvers[$resolver]) && class_exists($this->resolvers[$resolver]) && is_a($this->resolvers[$resolver], ResponseInterface::class, true)) {
                    //return new $this->resolvers[$resolver]($data);
                    $class = $this->resolvers[$resolver];
                }
                trigger_error("Type '{$data['type']} ({$resolver})' has resolver but it's not a valid class or callable. Using default class '{$class}'", E_USER_WARNING);
            } else {
                $className = $this->getNamespace() . '\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $data['action'])));
                if (class_exists($className) && is_a($className, ResponseInterface::class, true)) {
                    // return new $className($data);
                    $class = $className;
                } else {
                    $resolvers = implode(', ', array_keys($this->resolvers));
                    trigger_error("Type '{$data['type']} ({$resolver} -> [{$resolvers}])' has no resolver  or Custom class defined ('{$className}'). Using default class '{$class}'", E_USER_WARNING);
                }
            }


            return new $class($data);
        }
        throw new UnexpectedValueException('No response protocol ' . self::PROTOCOL->shortName() . ' detected. Must be one of: ' . implode(', ', $this->toArray()) . '');
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

    public static function getProtocolName(): ?string
    {
        return static::PROTOCOL->shortName();
    }
}
