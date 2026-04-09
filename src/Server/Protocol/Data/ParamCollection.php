<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;

class ParamCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return ParamDescriptor::class;
    }

    public function getRequired(): array
    {
        return $this->filter(fn(ParamDescriptor $param) => $param->required)->toArray();
    }

    public function getOptional(): array
    {
        return $this->filter(fn(ParamDescriptor $param) => !$param->required)->toArray();
    }
    public function getInjected(): array
    {
        return $this->filter(fn(ParamDescriptor $param) => $param->injected)->toArray();
    }
    public function collect(string $property): array
    {
        return array_map(static fn(ParamDescriptor $param) => $param->$property, $this->values);
    }

    public function collectRequired(string $property): array
    {
        return array_map(static fn(ParamDescriptor $param) => $param->$property, $this->getRequired());
    }

    public function collectOptional(string $property): array
    {
        return array_map(static fn(ParamDescriptor $param) => $param->$property, $this->getOptional());
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