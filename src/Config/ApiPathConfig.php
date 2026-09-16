<?php

namespace Tabula17\Satelles\Utilis\Config;

use Tabula17\Satelles\Utilis\Definition\HttpMethodEnum;
use Tabula17\Satelles\Utilis\Collection\BaseParamsCollection;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class ApiPathConfig extends AbstractDescriptor
{
    protected(set) string $name;
    protected(set) ?string $description = null;
    protected(set) ?string $responseDescription = null;
    protected(set) string $path {
        set {
            $this->path = '/' . ltrim($value, '/');
        }
    }
    protected(set) ?HttpMethodEnum $method = null
        {
            set(HttpMethodEnum|string|null $value) {
                if (is_string($value)) {
                    $value = HttpMethodEnum::fromString($value);
                }
                $this->method = $value;
            }
        }
    protected(set) array $headers = []
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
    protected(set) bool $requiresAuth = false;
    protected(set) bool $pathParams = false;
    protected(set) string $placeholder = ':%%name%%'; //{%%name%%} // etc ;
    protected(set) BaseParamsCollection $params
        {
            set (BaseParamsCollection|array $value) {
                if (is_array($value)) {
                    foreach ($value as $key => $param) {
                        if (!isset($param['xclass'])) {
                            $value[$key]['xclass'] = ApiParam::class;
                            if ($this->pathParams && !array_key_exists('pathParam', $param) && !array_key_exists('queryParam', $param)) {
                                $value[$key]['pathParam'] = true;
                            }
                        }
                    }
                    $value = BaseParamsCollection::fromArray($value);
                }
                $value->setPlaceholderMask($this->placeholder);
                $this->params = $value;
            }
            get {
                if (!isset($this->params)) {
                    $this->params = new BaseParamsCollection();
                    $this->params->setPlaceholderMask($this->placeholder);
                }
                return $this->params;
            }
        }
    protected(set) BaseParamsCollection $options //todo: generar una clase derviada de BaseOParams y BaseParamsCollection para opciones
        {
            set (BaseParamsCollection|array $value) {
                if (is_array($value)) {
                    $value = BaseParamsCollection::fromArray($value);
                }
                $this->options = $value;
            }
            get {
                if (!isset($this->options)) {
                    $this->options = new BaseParamsCollection();
                }
                return $this->options;
            }
        }
    protected(set) BaseParamsCollection $requestHeaders //todo: generar una clase derviada de BaseOParams y BaseParamsCollection para headers
        {
            set (BaseParamsCollection|array $value) {
                if (is_array($value)) {
                    $value = BaseParamsCollection::fromArray($value);
                }
                $this->requestHeaders = $value;
            }
            get {
                return $this->requestHeaders ?? new BaseParamsCollection();
            }
        }
    protected(set) string $baseUrl
        {
            set {
                if (!isset($this->baseUrl)) {
                    $this->baseUrl = rtrim($value, '/');
                }
            }
        }


    public function getQueryString(bool $onlyValid = true, bool $withPlaceholders = false): string
    {
        $params = $this->getQueryParams();
        return http_build_query($withPlaceholders ? $params->getPlaceholders($onlyValid) : $params->getValues($onlyValid));
    }

    public function getPathParamsString(bool $onlyValid = true, bool $withPlaceholders = false): string
    {
        $pathParams = $this->getPathParams();
        $params = $withPlaceholders ? $pathParams->getPlaceholders($onlyValid) : $pathParams->getValues($onlyValid);
        return '/' . implode('/', array_merge(...array_map(static fn($k, $v) => [$k, $v], array_keys($params), $params)));
    }

    public function getPathParams(): BaseParamsCollection
    {
        return $this->params->filter(fn($value, $key) => $value instanceof ApiParam && $value->pathParam);
    }

    public function getQueryParams(): BaseParamsCollection
    {
        return $this->params->filter(fn($value, $key) => $value instanceof ApiParam ? $value->queryParam : true);
    }

    public function endpointUrl(bool $withPathParams = false, bool $withPlaceholders = false): string
    {
        if ($withPathParams) {
            $pathParams = $this->getPathParams();
            if ($pathParams) {
                $paths = [];
                $pathParams->each(function ($value, $key) use ($withPlaceholders, &$paths) {
                    if ($value->required || $value->hasValue()) {
                        $paths[] = ($value->pathWithKey ? $value->name . '/' : '') . ($withPlaceholders ? $value->placeholder : $value->value);
                    }
                });
                return rtrim($this->baseUrl, '/') . '/' . trim($this->path, '/') . '/' . ltrim(implode('/', $paths) . '/');
            }
        }
        return rtrim($this->baseUrl, '/') . $this->path;
    }

}