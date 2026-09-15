<?php

namespace Tabula17\Satelles\Utilis\Collection;

use Tabula17\Satelles\Utilis\Config\ConnectionConfig;
use Tabula17\Satelles\Utilis\Exception\UnexpectedValueException;

/**
 * Represents a collection of ConnectionConfig objects with utility methods for finding, filtering,
 * and removing configurations based on specific criteria. This class ensures that only
 * ConnectionConfig objects are added to the collection.
 */
class ConnectionCollection extends TypedCollection
{

    final const string TYPE = ConnectionConfig::class;
    //public static string $type = ConnectionConfig::class;

    /**
     * @param string $name
     * @return ConnectionConfig|null
     */
    public function findByName(string $name): ConnectionConfig|null
    {
        return $this->findBy('name', $name);
    }

    public function removeByName(string $name): void
    {
        $this->removeBy('name', $name);
    }

    public function filterByHost(string $name): static
    {
        return $this->filterBy('host', $name);
    }

    public function findBy(string $key, mixed $value)
    {
        return $this->find(fn(ConnectionConfig $config) => $config->$key === $value);
    }

    public function filterBy(string $key, mixed $value): static
    {
        return $this->filter(fn(ConnectionConfig $config) => $config->$key === $value);
    }

    public function removeBy(string $key, mixed $value): void
    {
        $this->remove($this->findBy($key, $value));
    }

    public function collect(string $key): array
    {
        return array_filter(array_map(static fn(ConnectionConfig $config) => $config->$key, $this->values));
    }

    protected static function getType(): string
    {
        return static::TYPE;
    }

    /**
     * @throws UnexpectedValueException
     */
    public function reachableConnections(): static
    {
        return new static(...array_filter($this->values, static fn(ConnectionConfig $config) => $config->canConnect()));
    }

    /**
     * @throws UnexpectedValueException
     */
    public function unreachableConnections(): static
    {
        return new static(...array_filter($this->values, static fn(ConnectionConfig $config) => !$config->canConnect()));
    }

    /**
     * @throws UnexpectedValueException
     */
    public static function fromArray(array $config): static
    {
       // return new static(...array_map(static fn($item) => ConnectionConfig::fromArray($item), $config));
        $type = static::getType();

        return new static(...array_map(static fn($item) => $item instanceof $type ? $item : new $type($item), $config));
    }
}