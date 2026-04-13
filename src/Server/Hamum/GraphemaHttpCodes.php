<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Hamum;

enum GraphemaHttpCodes
{
    // 1xx: Informational
    case CONTINUE;
    case SWITCHING_PROTOCOLS;
    case PROCESSING;
    case EARLY_HINTS;

    // 2xx: Success
    case OK;
    case CREATED;
    case ACCEPTED;
    case NON_AUTHORITATIVE_INFORMATION;
    case NO_CONTENT;
    case RESET_CONTENT;
    case PARTIAL_CONTENT;
    case MULTI_STATUS;
    case ALREADY_REPORTED;
    case IM_USED;

    // 3xx: Redirection
    case MULTIPLE_CHOICES;
    case MOVED_PERMANENTLY;
    case FOUND;
    case SEE_OTHER;
    case NOT_MODIFIED;
    case USE_PROXY;
    case SWITCH_PROXY;
    case TEMPORARY_REDIRECT;
    case PERMANENT_REDIRECT;

    // 4xx: Client Errors
    case BAD_REQUEST;
    case UNAUTHORIZED;
    case PAYMENT_REQUIRED;
    case FORBIDDEN;
    case NOT_FOUND;
    case METHOD_NOT_ALLOWED;
    case NOT_ACCEPTABLE;
    case PROXY_AUTHENTICATION_REQUIRED;
    case REQUEST_TIMEOUT;
    case CONFLICT;
    case GONE;
    case LENGTH_REQUIRED;
    case PRECONDITION_FAILED;
    case PAYLOAD_TOO_LARGE;
    case URI_TOO_LONG;
    case UNSUPPORTED_MEDIA_TYPE;
    case RANGE_NOT_SATISFIABLE;
    case EXPECTATION_FAILED;
    case IM_A_TEAPOT;
    case MISDIRECTED_REQUEST;
    case UNPROCESSABLE_ENTITY;
    case LOCKED;
    case FAILED_DEPENDENCY;
    case TOO_EARLY;
    case UPGRADE_REQUIRED;
    case PRECONDITION_REQUIRED;
    case TOO_MANY_REQUESTS;
    case REQUEST_HEADER_FIELDS_TOO_LARGE;
    case UNAVAILABLE_FOR_LEGAL_REASONS;

    // 5xx: Server Errors
    case INTERNAL_SERVER_ERROR;
    case NOT_IMPLEMENTED;
    case BAD_GATEWAY;
    case SERVICE_UNAVAILABLE;
    case GATEWAY_TIMEOUT;
    case HTTP_VERSION_NOT_SUPPORTED;
    case VARIANT_ALSO_NEGOTIATES;
    case INSUFFICIENT_STORAGE;
    case LOOP_DETECTED;
    case NOT_EXTENDED;
    case NETWORK_AUTHENTICATION_REQUIRED;

    /**
     * Obtiene el enum a partir del código HTTP
     * Optimizado usando arrays asociativos con keys int
     */
    public static function get(int $code): self
    {
        static $map = [
            100 => self::CONTINUE,
            101 => self::SWITCHING_PROTOCOLS,
            102 => self::PROCESSING,
            103 => self::EARLY_HINTS,
            200 => self::OK,
            201 => self::CREATED,
            202 => self::ACCEPTED,
            203 => self::NON_AUTHORITATIVE_INFORMATION,
            204 => self::NO_CONTENT,
            205 => self::RESET_CONTENT,
            206 => self::PARTIAL_CONTENT,
            207 => self::MULTI_STATUS,
            208 => self::ALREADY_REPORTED,
            226 => self::IM_USED,
            300 => self::MULTIPLE_CHOICES,
            301 => self::MOVED_PERMANENTLY,
            302 => self::FOUND,
            303 => self::SEE_OTHER,
            304 => self::NOT_MODIFIED,
            305 => self::USE_PROXY,
            306 => self::SWITCH_PROXY,
            307 => self::TEMPORARY_REDIRECT,
            308 => self::PERMANENT_REDIRECT,
            400 => self::BAD_REQUEST,
            401 => self::UNAUTHORIZED,
            402 => self::PAYMENT_REQUIRED,
            403 => self::FORBIDDEN,
            404 => self::NOT_FOUND,
            405 => self::METHOD_NOT_ALLOWED,
            406 => self::NOT_ACCEPTABLE,
            407 => self::PROXY_AUTHENTICATION_REQUIRED,
            408 => self::REQUEST_TIMEOUT,
            409 => self::CONFLICT,
            410 => self::GONE,
            411 => self::LENGTH_REQUIRED,
            412 => self::PRECONDITION_FAILED,
            413 => self::PAYLOAD_TOO_LARGE,
            414 => self::URI_TOO_LONG,
            415 => self::UNSUPPORTED_MEDIA_TYPE,
            416 => self::RANGE_NOT_SATISFIABLE,
            417 => self::EXPECTATION_FAILED,
            418 => self::IM_A_TEAPOT,
            421 => self::MISDIRECTED_REQUEST,
            422 => self::UNPROCESSABLE_ENTITY,
            423 => self::LOCKED,
            424 => self::FAILED_DEPENDENCY,
            425 => self::TOO_EARLY,
            426 => self::UPGRADE_REQUIRED,
            428 => self::PRECONDITION_REQUIRED,
            429 => self::TOO_MANY_REQUESTS,
            431 => self::REQUEST_HEADER_FIELDS_TOO_LARGE,
            451 => self::UNAVAILABLE_FOR_LEGAL_REASONS,
            500 => self::INTERNAL_SERVER_ERROR,
            501 => self::NOT_IMPLEMENTED,
            502 => self::BAD_GATEWAY,
            503 => self::SERVICE_UNAVAILABLE,
            504 => self::GATEWAY_TIMEOUT,
            505 => self::HTTP_VERSION_NOT_SUPPORTED,
            506 => self::VARIANT_ALSO_NEGOTIATES,
            507 => self::INSUFFICIENT_STORAGE,
            508 => self::LOOP_DETECTED,
            510 => self::NOT_EXTENDED,
            511 => self::NETWORK_AUTHENTICATION_REQUIRED,
        ];

        return $map[$code] ?? self::INTERNAL_SERVER_ERROR;
    }

    /**
     * Obtiene el código HTTP del enum
     * Usando match en lugar de array para evitar problemas con keys de Enum
     */
    public function httpCode(): int
    {
        return match ($this) {
            self::CONTINUE => 100,
            self::SWITCHING_PROTOCOLS => 101,
            self::PROCESSING => 102,
            self::EARLY_HINTS => 103,
            self::OK => 200,
            self::CREATED => 201,
            self::ACCEPTED => 202,
            self::NON_AUTHORITATIVE_INFORMATION => 203,
            self::NO_CONTENT => 204,
            self::RESET_CONTENT => 205,
            self::PARTIAL_CONTENT => 206,
            self::MULTI_STATUS => 207,
            self::ALREADY_REPORTED => 208,
            self::IM_USED => 226,
            self::MULTIPLE_CHOICES => 300,
            self::MOVED_PERMANENTLY => 301,
            self::FOUND => 302,
            self::SEE_OTHER => 303,
            self::NOT_MODIFIED => 304,
            self::USE_PROXY => 305,
            self::SWITCH_PROXY => 306,
            self::TEMPORARY_REDIRECT => 307,
            self::PERMANENT_REDIRECT => 308,
            self::BAD_REQUEST => 400,
            self::UNAUTHORIZED => 401,
            self::PAYMENT_REQUIRED => 402,
            self::FORBIDDEN => 403,
            self::NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::NOT_ACCEPTABLE => 406,
            self::PROXY_AUTHENTICATION_REQUIRED => 407,
            self::REQUEST_TIMEOUT => 408,
            self::CONFLICT => 409,
            self::GONE => 410,
            self::LENGTH_REQUIRED => 411,
            self::PRECONDITION_FAILED => 412,
            self::PAYLOAD_TOO_LARGE => 413,
            self::URI_TOO_LONG => 414,
            self::UNSUPPORTED_MEDIA_TYPE => 415,
            self::RANGE_NOT_SATISFIABLE => 416,
            self::EXPECTATION_FAILED => 417,
            self::IM_A_TEAPOT => 418,
            self::MISDIRECTED_REQUEST => 421,
            self::UNPROCESSABLE_ENTITY => 422,
            self::LOCKED => 423,
            self::FAILED_DEPENDENCY => 424,
            self::TOO_EARLY => 425,
            self::UPGRADE_REQUIRED => 426,
            self::PRECONDITION_REQUIRED => 428,
            self::TOO_MANY_REQUESTS => 429,
            self::REQUEST_HEADER_FIELDS_TOO_LARGE => 431,
            self::UNAVAILABLE_FOR_LEGAL_REASONS => 451,
            self::INTERNAL_SERVER_ERROR => 500,
            self::NOT_IMPLEMENTED => 501,
            self::BAD_GATEWAY => 502,
            self::SERVICE_UNAVAILABLE => 503,
            self::GATEWAY_TIMEOUT => 504,
            self::HTTP_VERSION_NOT_SUPPORTED => 505,
            self::VARIANT_ALSO_NEGOTIATES => 506,
            self::INSUFFICIENT_STORAGE => 507,
            self::LOOP_DETECTED => 508,
            self::NOT_EXTENDED => 510,
            self::NETWORK_AUTHENTICATION_REQUIRED => 511,
        };
    }

    /**
     * Obtiene el mensaje del enum
     * Usando match en lugar de array para evitar problemas con keys de Enum
     */
    public function message(): string
    {
        return match ($this) {
            self::CONTINUE => 'Continue',
            self::SWITCHING_PROTOCOLS => 'Switching Protocols',
            self::PROCESSING => 'Processing',
            self::EARLY_HINTS => 'Early Hints',
            self::OK => 'OK',
            self::CREATED => 'Created',
            self::ACCEPTED => 'Accepted',
            self::NON_AUTHORITATIVE_INFORMATION => 'Non-Authoritative Information',
            self::NO_CONTENT => 'No Content',
            self::RESET_CONTENT => 'Reset Content',
            self::PARTIAL_CONTENT => 'Partial Content',
            self::MULTI_STATUS => 'Multi-Status',
            self::ALREADY_REPORTED => 'Already Reported',
            self::IM_USED => 'IM Used',
            self::MULTIPLE_CHOICES => 'Multiple Choices',
            self::MOVED_PERMANENTLY => 'Moved Permanently',
            self::FOUND => 'Found',
            self::SEE_OTHER => 'See Other',
            self::NOT_MODIFIED => 'Not Modified',
            self::USE_PROXY => 'Use Proxy',
            self::SWITCH_PROXY => 'Switch Proxy',
            self::TEMPORARY_REDIRECT => 'Temporary Redirect',
            self::PERMANENT_REDIRECT => 'Permanent Redirect',
            self::BAD_REQUEST => 'Bad Request',
            self::UNAUTHORIZED => 'Unauthorized',
            self::PAYMENT_REQUIRED => 'Payment Required',
            self::FORBIDDEN => 'Forbidden',
            self::NOT_FOUND => 'Not Found',
            self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
            self::NOT_ACCEPTABLE => 'Not Acceptable',
            self::PROXY_AUTHENTICATION_REQUIRED => 'Proxy Authentication Required',
            self::REQUEST_TIMEOUT => 'Request Timeout',
            self::CONFLICT => 'Conflict',
            self::GONE => 'Gone',
            self::LENGTH_REQUIRED => 'Length Required',
            self::PRECONDITION_FAILED => 'Precondition Failed',
            self::PAYLOAD_TOO_LARGE => 'Payload Too Large',
            self::URI_TOO_LONG => 'URI Too Long',
            self::UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
            self::RANGE_NOT_SATISFIABLE => 'Range Not Satisfiable',
            self::EXPECTATION_FAILED => 'Expectation Failed',
            self::IM_A_TEAPOT => "I'm a teapot",
            self::MISDIRECTED_REQUEST => 'Misdirected Request',
            self::UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
            self::LOCKED => 'Locked',
            self::FAILED_DEPENDENCY => 'Failed Dependency',
            self::TOO_EARLY => 'Too Early',
            self::UPGRADE_REQUIRED => 'Upgrade Required',
            self::PRECONDITION_REQUIRED => 'Precondition Required',
            self::TOO_MANY_REQUESTS => 'Too Many Requests',
            self::REQUEST_HEADER_FIELDS_TOO_LARGE => 'Request Header Fields Too Large',
            self::UNAVAILABLE_FOR_LEGAL_REASONS => 'Unavailable For Legal Reasons',
            self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
            self::NOT_IMPLEMENTED => 'Not Implemented',
            self::BAD_GATEWAY => 'Bad Gateway',
            self::SERVICE_UNAVAILABLE => 'Service Unavailable',
            self::GATEWAY_TIMEOUT => 'Gateway Timeout',
            self::HTTP_VERSION_NOT_SUPPORTED => 'HTTP Version Not Supported',
            self::VARIANT_ALSO_NEGOTIATES => 'Variant Also Negotiates',
            self::INSUFFICIENT_STORAGE => 'Insufficient Storage',
            self::LOOP_DETECTED => 'Loop Detected',
            self::NOT_EXTENDED => 'Not Extended',
            self::NETWORK_AUTHENTICATION_REQUIRED => 'Network Authentication Required',
        };
    }

    /**
     * Verifica si es un código informativo (1xx)
     */
    public function isInformational(): bool
    {
        $code = $this->httpCode();
        return $code >= 100 && $code < 200;
    }

    /**
     * Verifica si es un código de éxito (2xx)
     */
    public function isSuccess(): bool
    {
        $code = $this->httpCode();
        return $code >= 200 && $code < 300;
    }

    /**
     * Verifica si es un código de redirección (3xx)
     */
    public function isRedirection(): bool
    {
        $code = $this->httpCode();
        return $code >= 300 && $code < 400;
    }

    /**
     * Verifica si es un error del cliente (4xx)
     */
    public function isClientError(): bool
    {
        $code = $this->httpCode();
        return $code >= 400 && $code < 500;
    }

    /**
     * Verifica si es un error del servidor (5xx)
     */
    public function isServerError(): bool
    {
        $code = $this->httpCode();
        return $code >= 500 && $code < 600;
    }

    /**
     * Verifica si es un error (4xx o 5xx)
     */
    public function isError(): bool
    {
        return $this->isClientError() || $this->isServerError();
    }

    /**
     * Verifica si la respuesta es exitosa (2xx)
     */
    public function isOk(): bool
    {
        return $this->isSuccess();
    }

    /**
     * Obtiene la categoría del código HTTP
     */
    public function getCategory(): string
    {
        return match(true) {
            $this->isInformational() => 'Informational',
            $this->isSuccess() => 'Success',
            $this->isRedirection() => 'Redirection',
            $this->isClientError() => 'Client Error',
            $this->isServerError() => 'Server Error',
            default => 'Unknown'
        };
    }

    /**
     * Intenta obtener un enum a partir del código, lanza excepción si no existe
     */
    public static function getOrFail(int $code): self
    {
        $enum = self::get($code);

        if ($enum->httpCode() !== $code) {
            throw new \InvalidArgumentException("HTTP code {$code} is not valid");
        }

        return $enum;
    }

    /**
     * Verifica si un código HTTP es válido
     */
    public static function isValidCode(int $code): bool
    {
        return self::get($code)->httpCode() === $code;
    }

    /**
     * Obtiene todos los códigos HTTP disponibles
     */
    public static function getAllCodes(): array
    {
        return array_map(static fn($case) => $case->httpCode(), self::cases());
    }

    public function json(): array
    {
        return [
            'code' => $this->httpCode(),
            'message' => $this->message(),
            'category' => $this->getCategory(),
        ];
    }

    public function jsonString(): string
    {
        return json_encode($this->json(), JSON_PRETTY_PRINT);
    }

    public function html(): string
    {
        $category = $this->getCategory();
        $color = match($category) {
            'Success' => '#4CAF50',
            'Redirection' => '#2196F3',
            'Client Error' => '#FF9800',
            'Server Error' => '#F44336',
            default => '#9E9E9E'
        };

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$this->httpCode()} {$this->message()}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background: #f5f5f5;
        }
        h1 {
            font-size: 72px;
            margin: 0;
            color: {$color};
        }
        p {
            font-size: 24px;
            color: #666;
        }
        footer {
            margin-top: 50px;
            font-size: 14px;
            color: #999;
        }
    </style>
</head>
<body>
    <h1>{$this->httpCode()}</h1>
    <p>{$this->message()}</p>
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