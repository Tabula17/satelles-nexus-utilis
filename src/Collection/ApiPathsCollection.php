<?php

namespace Tabula17\Satelles\Utilis\Collection;

use Tabula17\Satelles\Utilis\Definition\HttpMethodEnum;
use Tabula17\Satelles\Utilis\Config\ApiPathConfig;
use Throwable;

class ApiPathsCollection extends TypedCollection
{

    protected static function getType(): string
    {
        return ApiPathConfig::class;
    }

    public function findByMethod(string $method)
    {
        return $this->find(fn(ApiPathConfig $config) => $config->method === HttpMethodEnum::fromString($method));
    }

    public function findByName(string $name)
    {
        return $this->find(fn(ApiPathConfig $config) => $config->name === $name);
    }

    public function collect(string $key): array
    {
        return $this->map(fn(ApiPathConfig $config) => $config->{$key});
    }

    public function definedPathNames(): array
    {
        return $this->map(fn(ApiPathConfig $config) => $config->name);
    }

    public static function fromArray(array $config, ?string $baseUrl = null): static
    {
        $values = [];

        foreach ($config as $key => $item) {
            try {
                if ($baseUrl) {
                    $item['baseUrl'] = $baseUrl;
                }
                $values[$key] = static::cast($item, ApiPathConfig::class, true);
            } catch (Throwable $e) {
                continue;
            }
        }

        return new static(...$values);
    }
}