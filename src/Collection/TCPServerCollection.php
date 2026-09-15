<?php

namespace Tabula17\Satelles\Utilis\Collection;

use Tabula17\Satelles\Utilis\Collection\TypedCollection;
use Tabula17\Satelles\Utilis\Config\TCPServerConfig;


/**
 * Represents a collection of TCPServerConfig objects with utility methods for filtering,
 * searching, and manipulating the collection by specific properties.
 */
class TCPServerCollection extends TypedCollection
{

    final const string TYPE  = TCPServerConfig::class;
    /**
     * @param string $host
     * @return TCPServerConfig|null
     */
    public function findByHost(string $host): TCPServerConfig|null
    {
        return $this->findBy('host', $host);
    }

    public function removeByHost(string $host): void
    {
        $this->removeBy('host', $host);
    }

    public function filterByHost(string $host): static
    {
        return $this->filterBy('host', $host);
    }

    public function findBy(string $key, mixed $value)
    {
        return $this->find(fn(TCPServerConfig $config) => $config->$key === $value);
    }

    public function filterBy(string $key, mixed $value): static
    {
        return $this->filter(fn(TCPServerConfig $config) => $config->$key === $value);
    }

    public function removeBy(string $key, mixed $value): void
    {
        $this->remove($this->findBy($key, $value));
    }

    public function collect(string $key): array
    {
        return array_filter(array_map(static fn(TCPServerConfig $config) => $config->$key, $this->values));
    }

    protected static function getType(): string
    {
        return static::TYPE;
    }
}