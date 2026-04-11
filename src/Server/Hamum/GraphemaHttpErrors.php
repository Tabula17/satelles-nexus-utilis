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
    case PAYMENT_REQUIRED;
    case NOT_ACCEPTABLE;
    case CONFLICT;
    case GONE;
    case UNPROCESSABLE_ENTITY;
    case TOO_MANY_REQUESTS;
    case BAD_GATEWAY;
    case SERVICE_UNAVAILABLE;
    case GATEWAY_TIMEOUT;

    public static function get(int $code): self
    {
        return match ($code) {
            404 => self::NOT_FOUND,
            405 => self::METHOD_NOT_ALLOWED,
            400 => self::BAD_REQUEST,
            401 => self::UNAUTHORIZED,
            403 => self::FORBIDDEN,
            402 => self::PAYMENT_REQUIRED,
            406 => self::NOT_ACCEPTABLE,
            409 => self::CONFLICT,
            410 => self::GONE,
            422 => self::UNPROCESSABLE_ENTITY,
            429 => self::TOO_MANY_REQUESTS,
            502 => self::BAD_GATEWAY,
            503 => self::SERVICE_UNAVAILABLE,
            504 => self::GATEWAY_TIMEOUT,
            default => self::INTERNAL_SERVER_ERROR,
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
            self::PAYMENT_REQUIRED => 402,
            self::NOT_ACCEPTABLE => 406,
            self::CONFLICT => 409,
            self::GONE => 410,
            self::UNPROCESSABLE_ENTITY => 422,
            self::TOO_MANY_REQUESTS => 429,
            self::BAD_GATEWAY => 502,
            self::SERVICE_UNAVAILABLE => 503,
            self::GATEWAY_TIMEOUT => 504,
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
            self::PAYMENT_REQUIRED => 'Payment Required',
            self::NOT_ACCEPTABLE => 'Not Acceptable',
            self::CONFLICT => 'Conflict',
            self::GONE => 'Gone',
            self::UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
            self::TOO_MANY_REQUESTS => 'Too Many Requests',
            self::BAD_GATEWAY => 'Bad Gateway',
            self::SERVICE_UNAVAILABLE => 'Service Unavailable',
            self::GATEWAY_TIMEOUT => 'Gateway Timeout',
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
        return <<<HTML
 <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error {$this->httpCode()}</title>
    </head>
    <body>
        <h1>{$this->httpCode()} {$this->message()}</h1>
        <footer>
            <p>&copy; Nexus Graphema Server by Tabula 17</p>
        </footer>
       </body>
    </html>
 HTML;
    }

    public function fromPath(string $path = './'): string
    {
        $file = rtrim($path, '/') . '/' . $this->httpCode() . '.html';
        if (file_exists($file)) {
            return file_get_contents($file);
        }

        return $this->html();
    }
}
