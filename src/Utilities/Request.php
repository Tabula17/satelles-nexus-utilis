<?php

namespace Tabula17\Satelles\Utilis\Utilities;

use Tabula17\Satelles\Utilis\Definition\HttpMethodEnum;
use Tabula17\Satelles\Utilis\File\MimeTypes;

class Request
{
    public HttpMethodEnum $method
        {
            set(string|HttpMethodEnum $method) {
                if (is_string($method)) {
                    $method = HttpMethodEnum::fromString($method);
                }
                $this->method = $method;
            }
        }
    public array $params = [];
    public array $files = [];
    private ?MimeTypes $contentType
        {
            set(string|MimeTypes|null $mimeTypes) {
                if (is_string($mimeTypes)) {
                    $mimeTypes = MimeTypes::fromMime($mimeTypes);
                }
                $this->contentType = $mimeTypes;
            }
        }

    public function __construct()
    {
        // 1. Detectar el método HTTP real (soporta sobreescritura común en APIs)
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($this->method->isPost() && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $this->method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }

        $this->contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        // 2. Procesar los datos
        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        // Siempre capturar parámetros de la URL (?user=123)
        $urlParams = $_GET ?? [];

        $bodyParams = [];
        $rawBody = file_get_contents('php://input');

        // Caso A: Petición con JSON (El estándar moderno para APIs)
        if ($this->contentType->isJson()) {
            $bodyParams = json_decode($rawBody, true) ?? [];
        } // Caso B: Formulario tradicional con archivos (multipart/form-data)
        elseif ($this->contentType->isMultipartFormData()) {
            if ($this->method->isPost()) {
                // PHP nativo procesa POST multipart automáticamente
                $bodyParams = $_POST;
                $this->files = $_FILES;
            } else {
                // PUT, PATCH y DELETE no llenan $_POST/$_FILES automáticamente si es multipart.
                // Llamamos a un helper para parsear el stream crudo.
                $this->parseMultipartStream($rawBody);
                $bodyParams = $this->params; // se llenan en el helper
            }
        } // Caso C: Formulario tradicional sin archivos (urlencoded)
        else if ($this->method->isPost()) {
            $bodyParams = $_POST;
        } else {
            parse_str($rawBody, $bodyParams);
        }

        // 3. Unificar parámetros para el pre-procesamiento posterior
        $cliParams = [];
        if (PHP_SAPI === 'cli') {
            $argv = $_SERVER['argv'] ?? [];
            foreach (array_slice($argv, 1) as $arg) {
                if (str_contains($arg, '=')) {
                    [$key, $value] = explode('=', $arg, 2);
                    $key = ltrim($key, '-');
                    $cliParams[$key] = $value;
                } else {
                    $key = ltrim($arg, '-');
                    if ($key !== '') {
                        $cliParams[$key] = true;
                    }
                }
            }
        }

        $this->params = array_merge($urlParams, $bodyParams, $cliParams);
    }

    /**
     * Helper para parsear multipart/form-data en métodos PUT/PATCH/DELETE
     */
    private function parseMultipartStream(string $rawBody): void
    {
        // Encontrar el boundary separador
        preg_match('/boundary=(.*)$/', $this->contentType, $matches);
        if (empty($matches[1])) {
            return;
        }
        $boundary = $matches[1];

        // Dividir el cuerpo por bloques usando el boundary
        $blocks = preg_split("/-+" . preg_quote($boundary, '/') . "/", $rawBody);
        array_pop($blocks); // Eliminar el último elemento vacío

        foreach ($blocks as $block) {
            if (empty(trim($block))) {
                continue;
            }

            // Separar encabezados del contenido del bloque
            [$headers, $body] = explode("\r\n\r\n", $block, 2);
            $body = substr($body, 0, -2); // Quitar el \r\n final
            // Buscar el nombre del campo
            if (preg_match('/name="([^"]+)"/', $headers, $nameMatch)) {
                $name = $nameMatch[1];
                // ¿Es un archivo?
                if (preg_match('/filename="([^"]+)"/', $headers, $fileMatch)) {
                    $filename = $fileMatch[1];
                    preg_match('/Content-Type:\s*([^\s]+)/', $headers, $typeMatch);

                    // Crear una estructura idéntica a $_FILES
                    $this->files[$name] = [
                        'name' => $filename,
                        'type' => $typeMatch[1] ?? 'application/octet-stream',
                        'size' => strlen($body),
                        'content' => $body // Guardamos el contenido temporal en memoria
                    ];
                } else {
                    // Es un parámetro de texto normal
                    $this->params[$name] = $body;
                }
            }
        }
    }
}
