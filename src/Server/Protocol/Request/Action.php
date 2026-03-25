<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request;

use Tabula17\Satelles\Nexus\Utilis\Exception\UnexpectedValueException;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

/**
 * ACTION_LIST_RPC_METHODS = 'list_rpc_methods';
 * ACTION_RPC_CALL = 'rpc';
 * ACTION_SUBSCRIBE = 'subscribe';
 * ACTION_UNSUBSCRIBE = 'unsubscribe';
 * ACTION_PUBLISH = 'publish';
 * ACTION_SEND_FILE = 'send_file';
 * ACTION_START_FILE_TRANSFER = 'start_file_transfer';
 * ACTION_FILE_CHUNK = 'file_chunk';
 * ACTION_REQUEST_FILE = 'request_file';
 * ACTION_AUTHENTICATE = 'authenticate';
 */
class Action extends AbstractDescriptor
{
    const string PROTOCOL = 'generic';
    private array $resolvers {
        set(array $resolvers) {
            $resolvers = array_filter($resolvers, fn($resolver) => $this->offsetExists($resolver), ARRAY_FILTER_USE_KEY);
            $this->resolvers = $resolvers;
        }
    }

    public function addResolver(string $name, string|callable $resolver): void
    {
        if (!$this->offsetExists($name)) {
            return;
        }
        $resolvers = $this->resolvers ?? [];
        $resolvers[$name] = $resolver;
        $this->resolvers = $resolvers;
    }

    public function getProtocolFor(array $data, ?int $fd, ?HamumServerInterface $server = null, ?ProtocolManagerInterface $protocolManager = null): RequestHandlerInterface|Status
    {
        if (isset($data['action']) && in_array($data['action'], $this->toArray())) {
            $resolver = array_search($data['action'], $this->toArray(), true);
            $class = Base::class;

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
            } else {
                $className = $this->getNamespace() . '\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $data['action'])));
                if (class_exists($className) && is_a($className, RequestHandlerInterface::class, true)) {
                    // return new $className($data);
                    $class = $className;
                }
            }
            return new $class($data);
        }
        throw new UnexpectedValueException('No action for protocol ' . static::PROTOCOL . ' -> {' . ($data['action'] ?? 'noType') . '} detected. Must be one of: ' . implode(', ', $this->toArray()));
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
        return static::PROTOCOL;
    }
}