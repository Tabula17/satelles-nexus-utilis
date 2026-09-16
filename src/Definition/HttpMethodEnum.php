<?php

namespace Tabula17\Satelles\Utilis\Definition;

use JsonSerializable;

enum HttpMethodEnum implements JsonSerializable
{
    case GET;
    case POST;
    case PUT;
    case DELETE;
    case PATCH;
    case HEAD;
    case OPTIONS;
    case TRACE;
    case CONNECT;

    public function value(): string
    {
        return $this->name;
    }

    public function lower(): string
    {
        return strtolower($this->name);
    }

    public function description(): string
    {
        return match ($this) {
            self::GET => 'Obtiene un recurso',
            self::POST => 'Crea un recurso',
            self::PUT => 'Actualiza un recurso',
            self::DELETE => 'Elimina un recurso',
            self::PATCH => 'Modifica un recurso',
            self::HEAD => 'Obtiene la cabecera de un recurso',
            self::OPTIONS => 'Obtiene las opciones de un recurso',
            self::TRACE => 'Realiza un seguimiento de un recurso',
            self::CONNECT => 'Conecta a un recurso',
        };
    }

    public static function tryFrom(string|self $method): self
    {
        if (is_string($method)) {
            return self::fromString($method);
        }
        return $method;
    }
    public static function fromString(string $method): self
    {
        return match (strtoupper($method)) {
            'POST' => self::POST,
            'PUT' => self::PUT,
            'DELETE' => self::DELETE,
            'PATCH' => self::PATCH,
            'HEAD' => self::HEAD,
            'OPTIONS' => self::OPTIONS,
            'TRACE' => self::TRACE,
            'CONNECT' => self::CONNECT,
            default => self::GET,
        };
    }
    public function isNot(string $method): bool
    {
        return $this->value() !== $method;
    }
    public function is(string $method): bool
    {
        return $this->value() === $method;
    }
    public function isPost(): bool
    {
        return $this === self::POST;
    }
    public function isGet(): bool
    {
        return $this === self::GET;
    }
    public function isPut(): bool
    {
        return $this === self::PUT;
    }
    public function isPatch(): bool
    {
        return $this === self::PATCH;
    }
    public function isDelete(): bool
    {
        return $this === self::DELETE;
    }
    public function isHead(): bool
    {
        return $this === self::HEAD;
    }
    public function isOptions(): bool
    {
        return $this === self::OPTIONS;
    }
    public function isTrace(): bool
    {
        return $this === self::TRACE;
    }
    public function isConnect(): bool
    {
        return $this === self::CONNECT;
    }

    public function jsonSerialize(): mixed
    {
        return $this->value();
    }
}
