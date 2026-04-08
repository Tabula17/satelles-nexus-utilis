<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data;

use Closure;
use Swoole\Table;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class MethodDescriptor extends AbstractDescriptor
{

    protected(set) string $method;
    protected(set) Closure $handler {
        set(callable|Closure $handler) {
            $this->handler = $handler instanceof Closure ? $handler : $handler(...);
        }
    }
    protected(set) bool $requires_auth = false;
    protected(set) array $allowed_roles = ['ws:general'];
    protected(set) string $description = 'RPC Method';
    protected(set) bool $only_internal = false;
    protected(set) bool $coroutine = true;
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

    public static function asTable(int $size = 1024): Table
    {
        /*
         *    $this->rpcMethods = new Table(512);
        $this->rpcMethods->column('name', Table::TYPE_STRING, 128);
        $this->rpcMethods->column('description', Table::TYPE_STRING, 255);
        $this->rpcMethods->column('requires_auth', Table::TYPE_INT, 1);
        $this->rpcMethods->column('registered_by_worker', Table::TYPE_INT);
        $this->rpcMethods->column('registered_at', Table::TYPE_INT);
        $this->rpcMethods->column('allowed_roles', Table::TYPE_STRING, 4096);
        $this->rpcMethods->column('only_internal', Table::TYPE_INT, 1);
        $this->rpcMethods->column('coroutine', Table::TYPE_INT, 1);
        $this->rpcMethods->create();
         */
        $table = new Table($size);
        $table->column('method', Table::TYPE_STRING, 255);
        $table->column('description', Table::TYPE_STRING, 255);
        $table->column('requires_auth', Table::TYPE_INT, 1);
        //$table->column('allowed_roles', Table::TYPE_STRING, 4096);
        $table->column('only_internal', Table::TYPE_INT, 1);
        $table->column('coroutine', Table::TYPE_INT, 1);
        //$table->column('parameters', Table::TYPE_STRING, 4096);
        //$table->column('returns', Table::TYPE_STRING, 4096);
        //$table->column('examples', Table::TYPE_STRING, 4096);
        $table->column('deprecated', Table::TYPE_INT, 1);
        $table->column('since', Table::TYPE_STRING, 20);
        $table->create();
        return $table;
    }

    public static function fromTable(array $row): self
    {
        return new self($row);
    }

    public function getPublicData(): array
    {
        if ($this->only_internal) {
            return [];
        }
        $values = $this->toArray();
        unset($values['handler']);
        $values['parameters'] = $this->parameters?->filter(fn($val) => !$val->injected)?->toArray() ?? [];

        return $values;
    }
}