<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Pars;

use Tabula17\Satelles\Utilis\Collection\GenericCollection;

class TictacusCollection extends GenericCollection
{

    protected static function getType(): string
    {
        return TictacusDescriptor::class;
    }

    public function getForOwner(string $owner): self
    {
        return $this->filter(fn(TictacusDescriptor $config) => $config->owner === $owner);
    }

    public function filterBy(string $key, mixed $value): self
    {
        return $this->filter(fn(TictacusDescriptor $config) => $config->$key === $value);
    }

    public function removeBy(string $key, mixed $value): void
    {
        $toRemove = $this->filterBy($key, $value);
        foreach ($toRemove as $id => $config) {
            $this->offsetUnset($id);
        }
    }
    public function findBy(string $key, mixed $value)
    {
        return $this->find(fn(TictacusDescriptor $config) => $config->$key === $value);
    }
    public function collect(string $key): array
    {
        return array_filter(array_map(static fn(TictacusDescriptor $config) => $config->$key, $this->values));
    }

}