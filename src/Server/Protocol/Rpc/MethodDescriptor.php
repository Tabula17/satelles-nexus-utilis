<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Rpc;

use Closure;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class MethodDescriptor extends AbstractDescriptor
{
    public function __construct(
        string                $method,
        array|Closure         $handler,
        string                $description = 'RPC Method',
        ParamCollection|array $parameters = [],
        bool                  $requiresAuth = false,
        array                 $allowedRoles = ['ws:general'],
        int                   $timeout = 10,
        array                 $returns = [],
        array                 $examples = [],
        bool                  $deprecated = false,
        string                $since = '0.0.1',
        string                $version = '1.0.0',
        ?string               $responseClass = null,
        bool                  $onlyInternal = false,
        bool                  $coroutine = true,
    )
    {

        parent::__construct(get_defined_vars());
    }

    protected(set) string $method;
    protected(set) Closure $handler {
        set(callable|Closure $handler) {
            $this->handler = $handler instanceof Closure ? $handler : $handler(...);
        }
    }
    protected(set) bool $requiresAuth = false;
    protected(set) array $allowedRoles = ['ws:general'];
    protected(set) string $description = 'RPC Method';
    protected(set) bool $onlyInternal = false;
    protected(set) bool $coroutine = true;
    protected(set) int $timeout;
    protected(set) ParamCollection $parameters
        {
            set(ParamCollection|array $parameters) {
                $this->parameters = is_array($parameters) ? ParamCollection::fromArray($parameters) : $parameters;
            }
            get {
                return $this->parameters ?? ParamCollection::fromArray([]);
            }
        }
    protected(set) array $returns = []
        {
            set {
                $default = [
                    'type' => 'mixed',
                    'description' => '',
                    'structure' => []
                ];
                $this->returns = array_merge($default, $value);
            }
        }
    protected(set) array $examples = [];
    protected(set) bool $deprecated = false;
    protected(set) string $since = '0.0.1'
        {
            set {
                if (version_compare($value, '0.0.1', '>=')) {
                    $this->since = $value;
                }
            }
        }
    protected(set) string $version = '1.0.0';
    protected(set) ?string $responseClass;

    public function getRequiredParams(): array
    {
        return $this->parameters->filter(fn($param) => $param->required)->toArray();
    }

    public function getPublicData(): array
    {
        if ($this->onlyInternal) {
            return [];
        }
        $values = $this->toArray();
        unset($values['handler'], $values['responseClass'], $values['onlyInternal'], $values['coroutine']);
        $values['parameters'] = $this->parameters?->getPublics()?->getPublicData() ?? [];

        return $values;
    }
}