<?php

namespace Tabula17\Satelles\Utilis\Config;

use Swoole\Coroutine\Http\Client;
use Tabula17\Satelles\Utilis\Definition\HttpMethodEnum;
use Tabula17\Satelles\Utilis\Collection\ApiPathsCollection;
use Tabula17\Satelles\Utilis\Config\ConnectionConfig;
use Tabula17\Satelles\Utilis\Config\SemVer;

class ApiConfig extends ConnectionConfig
{
    protected(set) string $basePath = '/'
        {
            get {
                return '/' . ltrim($this->basePath, '/');
            }
        }
    protected(set) ?HttpMethodEnum $method = HttpMethodEnum::GET
        {
            set(HttpMethodEnum|string|null $value) {
                if (is_string($value)) {
                    $value = HttpMethodEnum::fromString($value);
                }
                $this->method = $value;
            }
        }
    public string $protocol = 'http';
    protected(set) SemVer $version
        {
            set(string|SemVer $value) {
                if (is_string($value)) {
                    $value = ltrim($value, 'v');
                    $value = SemVer::fromString($value);
                }
                $this->version = $value;
            }
            get {
                return $this->version ?? SemVer::fromString('1.0.0');
            }
        }
    protected(set) array $headers = [
        "Accept" => "application/json",
        "Content-Type" => "application/json"
    ]
        {
            set {
                $value = array_map(
                    static function ($value) {
                        if (!is_string(is_callable($value) ? $value() : $value)) {
                            throw new \InvalidArgumentException('Los valores de los headers deben ser cadenas');
                        }
                        return $value;
                    }, $value
                );
                $this->headers = $value;
            }
            get {
                $normalizer = static function ($value) {
                    return is_callable($value) ? $value() : $value;
                };

                return array_map($normalizer, $this->headers);
            }
        }
    public array $headersAsStrings {
        get {
            $normalizer = static function ($value, $key) {
                return is_numeric($key) && str_contains($value, ":") ? ucfirst($value) : "$key: $value";
            };

            return array_map($normalizer, $this->headers, array_keys($this->headers));
        }

    }
    protected(set) ApiPathsCollection $apiPaths
        {
            set (ApiPathsCollection|array $value) {
                if (is_array($value)) {
                    $value = ApiPathsCollection::fromArray($value, $this->getBaseEndpoint());
                }
                $this->apiPaths = $value;
            }
            get {
                if (!isset($this->apiPaths)) {
                    $this->apiPaths = new ApiPathsCollection();
                }
                return $this->apiPaths;
            }
        }
    protected(set) int $timeout = 30;
    protected(set) ?string $apiKey;

    public function httpClient(): Client
    {
        $client = new Client($this->protocol . '://' . $this->host . $this->basePath);
        $client->set([
            'timeout' => $this->timeout
        ]);
        $client->setHeaders($this->headers);
        return $client;

    }

    public function getEndpoint(string $endpoint): ?ApiPathConfig
    {
        return $this->apiPaths->findByName($endpoint);//?->path;
        // return $this->protocol . '://' . $this->host . $this->basePath . ltrim(($path ?? ''), '/');
    }

    public function getBaseEndpoint(): string
    {
        $baseUrl = $this->host;
        if ($this->port !== null) {
            $baseUrl .= ':' . $this->port;
        }
        return $this->protocol . '://' . $baseUrl . $this->basePath;
    }

    public function getAvailableEndpoints(): array
    {
        return $this->apiPaths->definedPathNames();
    }
}