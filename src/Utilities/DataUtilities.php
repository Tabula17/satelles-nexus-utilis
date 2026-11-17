<?php

namespace Tabula17\Satelles\Utilis\Utilities;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Random\RandomException;
use Tabula17\Satelles\Utilis\Definition\HttpMethodEnum;
use Tabula17\Satelles\Utilis\Exception\InvalidArgumentException;

class DataUtilities
{


    private function __construct()
    {
    }

    public static function sanitizeNumericString($string): array|string|null
    {
        return $string ? preg_replace('/[^0-9]/', '', $string) : null;
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

    private static function normalizeHeaderName(string $name): string
    {
        $name = str_replace(['_', ' '], '-', trim($name));
        return str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $name))));
    }

    public static function normalizeRequestHeaders(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $rawHeaders = getallheaders();
            if (is_array($rawHeaders) && !empty($rawHeaders)) {
                foreach ($rawHeaders as $key => $value) {
                    if (is_int($key) || is_numeric($key)) {
                        if (is_string($value) && str_contains($value, ':')) {
                            [$headerKey, $headerVal] = explode(':', $value, 2);
                            $name = self::normalizeHeaderName($headerKey);
                            $headers[$name] = trim($headerVal);
                        }
                    } else {
                        $name = self::normalizeHeaderName((string)$key);
                        $headers[$name] = is_string($value) ? trim($value) : $value;
                    }
                }
            }
        }

        if (empty($headers)) {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $name = self::normalizeHeaderName(substr($key, 5));
                    $headers[$name] = is_string($value) ? trim($value) : $value;
                } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                    $name = self::normalizeHeaderName($key);
                    $headers[$name] = is_string($value) ? trim($value) : $value;
                }
            }
        }

        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                $basicPass = $_SERVER['PHP_AUTH_PW'] ?? '';
                $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basicPass);
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = trim($_SERVER['PHP_AUTH_DIGEST']);
            }
        }

        return $headers;
    }

    public static function validateCUITFormat(string $cuit): bool
    {
        // 1. Eliminar caracteres no numéricos
        $cuit = preg_replace('/\D/', '', trim((string)$cuit));
        // 2. Verificar longitud (debe ser exactamente 11)
        if (preg_match('/^\d{11}$/', $cuit) !== 1) {
            return false;
        }
        // 3. Obtener el dígito verificador (último número)
        $digitoVerificador = (int)substr($cuit, -1);
        // 4. Extraer los primeros 10 dígitos y separarlos en un arreglo
        $cuitBase = substr($cuit, 0, 10);
        $numeros = str_split($cuitBase);
        // 5. Definir la serie de multiplicadores
        $multiplicadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        // 6. Realizar la suma de los productos
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int)$numeros[$i] * $multiplicadores[$i];
        }
        // 7. Calcular el resto de la división por 11
        $resto = $suma % 11;
        // 8. Calcular el dígito esperado según las reglas del algoritmo
        $digitoCalculado = 11 - $resto;
        if ($digitoCalculado === 11) {
            $digitoCalculado = 0;
        } elseif ($digitoCalculado === 10) {
            // En la práctica, si da 10 suele fallar la estructura general.
            return false;
        }
        // 9. Comparar el dígito calculado con el proporcionado
        return $digitoVerificador === $digitoCalculado;
    }

    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function cleanPhoneNumber(string $phone): ?string
    {
        // 1. Quitar caracteres visuales comunes (espacios, guiones, paréntesis, puntos, mas)
        $clean = preg_replace('/[\s\-\(\)\.\+]/', '', $phone);
        // 2. Remover prefijos internacionales si existen (54 o 549)
        if (str_starts_with($clean, '549')) {
            $clean = substr($clean, 3);
        } elseif (str_starts_with($clean, '54')) {
            $clean = substr($clean, 2);
        }
        // 3. Remover el 0 inicial de la característica si lo pusieron (ej: 011 -> 11)
        if (str_starts_with($clean, '0')) {
            $clean = substr($clean, 1);
        }
        // 4. Remover el "15" si quedó pegado antes del número local
        // Las características de Argentina tienen entre 2 y 4 dígitos.
        // Ejemplos comunes: 11 (2), 341 (3), 2966 (4).
        if (preg_match('/^(\d{2,4})(15)(\d{6,8})$/', $clean, $matches)) {
            // Si coincide, eliminamos el '15' uniendo el código de área ($matches[1]) con el número local ($matches[3])
            $clean = $matches[1] . $matches[3];
        }
        return $clean;
    }

    public static function validPhone(string $phone): false|int
    {
        $clean = self::cleanPhoneNumber($phone);
        if (preg_match('/^\d{10}$/', $clean) === 1) {
            return (int)$clean;
        }
        return false;
    }

    public static function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function validateCbuFormat(string|int $cbu): bool
    {
        return preg_match('/^\d{22}$/', (string)$cbu) === 1;
    }

    public static function validateAccountAlias(string $alias): bool
    {
        $alias = trim((string)$alias);
        //los alias deben tener entre 6 y 20 caracteres y pueden usar letras, números, guiones bajos y puntos
        return !is_numeric($alias) && preg_match('/^[a-zA-Z0-9._-]{6,20}$/', $alias) === 1;
    }

    public static function accountIsAlias(string $account): bool
    {
        return !self::validateCbuFormat($account) && self::validateAccountAlias($account);
    }

    public static function formatDate(string $date, string $format = 'Y-m-d'): string
    {
        return date($format, strtotime($date));
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function parseDate(string|DateTimeInterface $value): DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;//->format('Y-m-d');
        }
        $value = trim($value);
        $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
// ISO 8601 con zona/offset: 2026-09-16T14:00:08.000Z, ...+00:00, ...-03:00
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:?\d{2})$/i', $value)) {
            try {
                return (new DateTimeImmutable($value))->setTimezone($tz);
            } catch (\Exception) {
                throw new InvalidArgumentException("Formato de fecha no reconocido: {$value}");
            }
        }
        $patterns = [
            // ISO con hora: 2026-09-01T10:00:00 / 2026-09-01 10:00:00
            '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}$/' => 'Y-m-d H:i:s',
            // ISO solo fecha
            '/^\d{4}-\d{2}-\d{2}$/' => 'Y-m-d',
            // d-m-Y con hora
            '/^\d{2}[\/\-]\d{2}[\/\-]\d{4}[ T]\d{2}:\d{2}:\d{2}$/' => 'd-m-Y H:i:s',
            // d-m-Y solo fecha
            '/^\d{2}[\/\-]\d{2}[\/\-]\d{4}$/' => 'd-m-Y',
        ];

        foreach ($patterns as $regex => $fmt) {
            if (!preg_match($regex, $value)) {
                continue;
            }

            $normalized = str_replace(['/', 'T'], ['-', ' '], $value);
            $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $normalized, $tz);
            $errors = DateTimeImmutable::getLastErrors();

            if ($dt !== false
                && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ) {
                return $dt;
            }
        }

        throw new InvalidArgumentException("Formato de fecha no reconocido: {$value}");
    }

    public static function randomWord($len = 10): string
    {
        $word = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'));
        shuffle($word);
        return substr(implode($word), 0, $len);
    }

    /**
     * @throws RandomException
     */
    public static function randomFloat(): float|int
    {
        return (random_int(0, 10000) / 10000);
    }

}