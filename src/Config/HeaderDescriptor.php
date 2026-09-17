<?php

namespace Tabula17\Satelles\Utilis\Config;

use Closure;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class HeaderDescriptor extends AbstractDescriptor
{
    protected(set) string $name;
    protected(set) string|Closure $value
        {
            set {
                if ($this->readonly && isset($this->value)) {
                    trigger_error('Cannot set value of readonly header');
                    return;
                }
                $this->value = $value;
            }
            get {
                return is_callable($this->value) ? call_user_func($this->value) : $this->value;
            }
        }


    public function __construct(string $name, string|Closure $value, protected bool $readonly = false)
    {
        $this->name = $name;
        $this->value = $value;
        parent::__construct();
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    public function asArray(): array
    {
        return [$this->name => $this->value];
    }

    public function __toString(): string
    {
        return $this->name . ': ' . $this->value;
    }
}