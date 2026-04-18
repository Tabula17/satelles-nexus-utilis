<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Processor;

use Closure;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class ParamDescriptor extends AbstractDescriptor
{

    protected(set) string $name;
    protected(set) string $type = 'string';
    protected(set) string $description = 'RPC parameter';
    protected(set) mixed $default = null;
    protected(set) bool $required = false;
    protected(set) mixed $example = null;
    protected(set) string|null|Closure $validation = null;
    protected(set) array $enum;
    protected(set) bool $injected = false;
    public mixed $value {
        get {
            return $this->value;
        }
        set {
            settype($value, $this->type);
            $this->value = $value;
        }
    }

    public function getPublicData(): array
    {

        $values = $this->toArray();
        unset($values['injected']);
        if (isset($values['value'])) {
            unset($values['value']);
        }
        if (isset($values['validation'])) {
            unset($values['validation']);
        }
        return $values;
    }
}