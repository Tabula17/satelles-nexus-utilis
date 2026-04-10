<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data;

use JsonSerializable;
use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class ParamCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return ParamDescriptor::class;
    }

    public function getRequired(): self
    {
        return $this->filter(fn(ParamDescriptor $param) => $param->required);
    }

    public function getOptional(): self
    {
        return $this->filter(fn(ParamDescriptor $param) => !$param->required);
    }
    public function getInjected(): self
    {
        return $this->filter(fn(ParamDescriptor $param) => $param->injected);
    }
    public function collect(string $property): array
    {
        //return array_map(static fn(ParamDescriptor $param) => $param->$property, $this->values);
        return array_filter(array_map(static fn(ParamDescriptor $param) => $param->$property, $this->values));
    }

    public function collectRequired(string $property): array
    {
        return $this->getRequired()->collect($property);
    }

    public function collectOptional(string $property): array
    {
        return $this->getRequired()->collect($property);
    }
    public function setParamValue(string $name, mixed $value): void
    {
        $this->find(static fn(ParamDescriptor $param) => $param->name === $name)->value = $value;
    }
    public function getValues(): array
    {
        $collection = $this->filter(static fn(ParamDescriptor $param) => isset($param->value));
        $params = [];
        foreach ($collection as $param) {
            $params[$param->name] = $param->value;
        }
        return $params;
    }
}