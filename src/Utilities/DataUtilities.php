<?php

namespace Tabula17\Satelles\Utilis\Utilities;

use Tabula17\Satelles\Utilis\Definition\HttpMethodEnum;

class DataUtilities
{


    private function __construct()
    {
    }

    public static function sanitizeNumericString($string): array|string|null
    {
        return preg_replace('/[^0-9]/', '', $string);
    }

    public static function normalizePostData(): array
    {
        // 1. Obtener datos tradicionales de formulario
        $data = $_POST ?? [];
        // 2. Leer el flujo de entrada crudo
        $raw_data = file_get_contents('php://input');
        if (!empty($raw_data)) {
            // 3. Intentar decodificar el JSON
            $post_data = json_decode($raw_data, true);
            // 4. Solo fusionar si el JSON se decodificó correctamente como array
            if (is_array($post_data)) {
                $data = array_merge($data, $post_data);
            }
        }

        return $data;
    }
    public static function getRequestPayload(HttpMethodEnum $method)
    {
        return match ($method) {
            HttpMethodEnum::GET => $_GET ?? [],

        };
    }

    public static function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}