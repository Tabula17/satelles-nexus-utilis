<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Hamum;

enum GraphemaHttpErrors
{
    case NOT_FOUND;
    case METHOD_NOT_ALLOWED;
    case INTERNAL_SERVER_ERROR;
    case BAD_REQUEST;
    case UNAUTHORIZED;
    case FORBIDDEN;
    public static function get(int $code): self
    {
        return match ($code) {
            404 => self::NOT_FOUND,
            405 => self::METHOD_NOT_ALLOWED,
            500 => self::INTERNAL_SERVER_ERROR,
            400 => self::BAD_REQUEST,
            401 => self::UNAUTHORIZED,
            403 => self::FORBIDDEN,
            default => throw new \Exception('Unknown error code'),
        };
    }
    public function httpCode(): int
    {
        return match ($this) {
            self::NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::INTERNAL_SERVER_ERROR => 500,
            self::BAD_REQUEST => 400,
            self::UNAUTHORIZED => 401,
            self::FORBIDDEN => 403,
        };
    }
    public function message(): string
    {
        return match ($this) {
            self::NOT_FOUND => 'Not Found',
            self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
            self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
            self::BAD_REQUEST => 'Bad Request',
            self::UNAUTHORIZED => 'Unauthorized',
            self::FORBIDDEN => 'Forbidden',
        };
    }
    public function json(): array
    {
        return [
            'code' => $this->httpCode(),
            'message' => $this->message(),
        ];
    }
    public function jsonString(): string
    {
        return json_encode($this->json());
    }
    public function html(): string
    {
        return "<h1>{$this->httpCode()} {$this->message()}</h1>";
    }
}
