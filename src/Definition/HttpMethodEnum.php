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
    public static function fromString(string $method): ?self
    {
        return match (strtoupper($method)) {
            'GET' => self::GET,
            'POST' => self::POST,
            'PUT' => self::PUT,
            'DELETE' => self::DELETE,
            'PATCH' => self::PATCH,
            'HEAD' => self::HEAD,
            'OPTIONS' => self::OPTIONS,
            'TRACE' => self::TRACE,
            'CONNECT' => self::CONNECT,
            default => null,
        };
    }

    public function jsonSerialize(): mixed
    {
        return $this->value();
    }
}
