<?php

declare(strict_types=1);

namespace Tabula17\Satelles\Nexus\Utilis\Client;

use JsonException;
use RuntimeException;
use Swoole\Coroutine\Client;
use Tabula17\Satelles\Nexus\Utilis\Protocol\FileServerActionsEnum;
use Tabula17\Satelles\Utilis\Config\TCPServerConfig;

class BasisFileClientTcp extends Client
{
    protected const int CHUNK_SIZE = 1048576; // 1MB

    // Constantes para el protocolo de framing
    protected const int FRAME_TYPE_DATA = 0x01;
    protected const int FRAME_TYPE_PROGRESS = 0x02;
    protected const int FRAME_TYPE_ERROR = 0x03;
    protected const int FRAME_TYPE_HEADER = 0x04;
    protected const int FRAME_TYPE_END = 0x05;

    //  Marcadores de tipo de respuesta
    final protected const int RESPONSE_TYPE_JSON = 0x00;
    final protected const int RESPONSE_TYPE_STREAM = 0x01;
    private string $readBuffer = '';

    public function __construct(
        protected TCPServerConfig $serverCfg,
        int                       $sockType = SOCK_STREAM
    )
    {
        parent::__construct($sockType);
    }

    /**
     * Envía un archivo junto con los metadatos del job
     *
     * @param string $filePath Ruta local del archivo a enviar
     * @param array $metadata Metadatos adicionales (action, outputFormat, etc.)
     * @return bool True si se envió correctamente
     * @throws RuntimeException
     */
    protected function sendFileWithMetadata(string $filePath, array $metadata): bool
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Archivo no encontrado: {$filePath}");
        }

        $fileSize = filesize($filePath);
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el archivo: {$filePath}");
        }

        try {
            $metadata['fileName'] = basename($filePath);
            $metadata['fileSize'] = $fileSize;

            $jsonMetadata = json_encode($metadata, JSON_THROW_ON_ERROR);
            $jsonLength = strlen($jsonMetadata);

            // ========== SOLUCIÓN: Todo el header en UN SOLO send() ==========
            $header = chr(0x01)                     // Marcador de tipo archivo
                . pack('N', $jsonLength)        // Longitud del JSON (4 bytes, big-endian)
                . $jsonMetadata                 // JSON de metadatos
                . pack('J', $fileSize);         // Tamaño del archivo (8 bytes, big-endian)

            // echo "[CLIENTE DEBUG] Header completo: " . strlen($header) . " bytes\n";
            // echo "[CLIENTE DEBUG] Primeros 30 bytes del header: " . bin2hex(substr($header, 0, 30)) . "\n";

            // Enviar TODO el header de UNA SOLA VEZ
            if (!$this->send($header)) {
                throw new RuntimeException('Error al enviar header');
            }
            // ================================================================

            // 5. Enviar archivo en chunks
            // echo "[CLIENTE DEBUG] Enviando archivo...\n";
            $sentBytes = 0;
            while (!feof($handle)) {
                $chunk = fread($handle, self::CHUNK_SIZE);

                if ($chunk === false) {
                    throw new RuntimeException('Error al leer archivo');
                }

                if (!$this->send($chunk)) {
                    throw new RuntimeException('Error al enviar chunk de archivo');
                }

                $sentBytes += strlen($chunk);
            }

            // echo "[CLIENTE DEBUG] Total enviado: {$sentBytes} bytes\n";
            return $sentBytes === $fileSize;

        } finally {
            fclose($handle);
        }
    }

    protected function sendJsonMessage(array $data): bool|int
    {
        $this->ensureConnected();

        // ✅ Enviar byte marcador de JSON (0x00) + JSON
        return $this->send(chr(0x00) . json_encode($data));
    }

    /**
     * Recibe respuesta y la guarda en memoria
     */
    protected function receiveResponseToMemory(): string
    {
        // Leer el primer byte (marcador de tipo)
        $typeByte = $this->recv(1);

        if ($typeByte === false || $typeByte === '') {
            throw new RuntimeException('Conexión cerrada inesperadamente');
        }
        $type = ord($typeByte);

        if ($type === self::RESPONSE_TYPE_JSON) {
            $json = $this->receiveJson();

            if (isset($json['error'])) {
                throw new RuntimeException('Error del servidor: ' . $json['error']);
            }

            if (isset($json['base64Content'])) {
                return base64_decode($json['base64Content']);
            }

            throw new RuntimeException('Respuesta no contiene contenido');
        }

        // Si no es JSON, es streaming de archivo (lo guardamos en memoria)
        return $this->receiveStreamToMemory();
    }

    /**
     * Recibe un archivo por streaming y lo devuelve como string
     */
    protected function receiveStreamToMemory(): string
    {
        $header = $this->recvExact(4);

        if ($header === false || strlen($header) < 4) {
            throw new RuntimeException('No se pudo leer el header del archivo');
        }

        $totalSize = unpack('N', $header)[1];

        if ($totalSize === 0) {
            throw new RuntimeException('El servidor reportó error (tamaño 0)');
        }

        $content = '';
        $receivedBytes = 0;

        while ($receivedBytes < $totalSize) {
            $remaining = $totalSize - $receivedBytes;
            $readSize = min(self::CHUNK_SIZE, $remaining);

            $chunk = $this->recv($readSize);

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Conexión interrumpida durante la transferencia');
            }

            $content .= $chunk;
            $receivedBytes += strlen($chunk);
        }

        if ($receivedBytes !== $totalSize) {
            throw new RuntimeException('Transferencia incompleta');
        }

        return $content;
    }

    /**
     * Recibe la respuesta del servidor (detecta si es JSON o streaming)
     */
    protected function receiveResponse(string $outputPath): bool
    {
        // Leer el primer byte (marcador de tipo)
        $typeByte = $this->recvExact(1);

        // echo "Type byte recibido: " . strlen($typeByte) . " bytes, hex: " . bin2hex($typeByte) . "\n";

        if ($typeByte === false || $typeByte === '') {
            throw new RuntimeException('Conexión cerrada inesperadamente');
        }
        $type = ord($typeByte[0]);

        if ($type === self::RESPONSE_TYPE_JSON) {
            $json = $this->receiveJson();

            if (isset($json['error'])) {
                throw new RuntimeException('Error del servidor: ' . $json['error']);
            }

            if (($json['status'] ?? '') === 'failed') {
                throw new RuntimeException('Conversión fallida: ' . ($json['message'] ?? 'Unknown error'));
            }
            // Si es una respuesta JSON exitosa pero no tiene archivo
            return true;
        }

        // Si no es JSON, es streaming de archivo
        return $this->receiveResponseWithFraming($outputPath, null);
    }

    /**
     * Recibe un archivo por streaming y lo guarda en disco
     */
    protected function receiveStreamToFile(string $outputPath): bool
    {
        $header = $this->recvExact(4);

        if ($header === false || strlen($header) < 4) {
            throw new RuntimeException('No se pudo leer el header del archivo');
        }

        $totalSize = unpack('N', $header)[1];
        // echo "Tamaño: {$totalSize}\n";

        if ($totalSize === 0) {
            throw new RuntimeException('El servidor reportó error (tamaño 0)');
        }

        $handle = fopen($outputPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('No se pudo crear archivo: ' . $outputPath);
        }

        $receivedBytes = 0;

        try {
            while ($receivedBytes < $totalSize) {
                $remaining = $totalSize - $receivedBytes;
                $readSize = min(self::CHUNK_SIZE, $remaining);

                $chunk = $this->recv($readSize);

                // ✅ Si recv devuelve vacío, el servidor cerró la conexión
                if ($chunk === false || $chunk === '') {
                    break;  // Salir del bucle, el servidor terminó de enviar
                }

                fwrite($handle, $chunk);
                $receivedBytes += strlen($chunk);

                //echo "Progreso: {$receivedBytes}/{$totalSize}\r";
            }

            return $receivedBytes === $totalSize;

        } finally {
            fclose($handle);
            if ($receivedBytes !== $totalSize) {
                @unlink($outputPath);
            }
        }
    }

    /**
     * Recibe respuesta con protocolo de framing (soporta progreso)
     *
     * @param string $outputPath Ruta donde guardar el archivo
     * @param callable|null $onProgress Callback para progreso
     * @return bool
     * @throws RuntimeException
     */
    protected function receiveResponseWithFraming(string $outputPath, ?callable $onProgress = null): bool
    {
        $handle = fopen($outputPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('No se pudo crear archivo: ' . $outputPath);
        }

        $totalSize = 0;
        $receivedBytes = 0;

        try {
            while (true) {
                // Leer tipo de frame (1 byte)
                $typeByte = $this->recvExact(1);

                if ($typeByte === false || $typeByte === '') {
                    break;
                }

                $type = ord($typeByte);

                // Leer longitud (4 bytes)
                $lengthBytes = $this->recvExact(4);

                if ($lengthBytes === false || strlen($lengthBytes) < 4) {
                    throw new RuntimeException('Frame incompleto: no se pudo leer longitud');
                }

                $length = unpack('N', $lengthBytes)[1];

                // Leer datos del frame
                $data = $this->recvExact($length);

                if ($data === false || strlen($data) < $length) {
                    throw new RuntimeException('Frame incompleto: datos insuficientes');
                }

                switch ($type) {
                    case self::FRAME_TYPE_HEADER:
                        $header = json_decode($data, true);
                        $totalSize = $header['size'] ?? 0;
                        break;

                    case self::FRAME_TYPE_DATA:
                        fwrite($handle, $data);
                        $receivedBytes += strlen($data);
                        break;

                    case self::FRAME_TYPE_PROGRESS:
                        if ($onProgress) {
                            $progress = json_decode($data, true);
                            $onProgress(
                                $progress['progress'] ?? 0,
                                $progress['sent'] ?? 0,
                                $progress['total'] ?? $totalSize
                            );
                        }
                        break;

                    case self::FRAME_TYPE_ERROR:
                        $error = json_decode($data, true);
                        throw new RuntimeException($error['error'] ?? 'Error desconocido');

                    case self::FRAME_TYPE_END:
                        $endData = json_decode($data, true);
                        return $endData['success'] ?? ($receivedBytes === $totalSize);

                    default:
                        throw new RuntimeException('Tipo de frame desconocido: ' . $type);
                }
            }

            return $receivedBytes === $totalSize;

        } catch (\Throwable $e) {
            throw new RuntimeException('Error recibiendo archivo: ' . $e->getMessage(), 0, $e);
        } finally {
            fclose($handle);

            if ($receivedBytes !== $totalSize && $totalSize > 0) {
                @unlink($outputPath);
            }
        }
    }

    /**
     * Lee exactamente N bytes del socket
     */
    protected function recvExact(int $length): string|false
    {
        // Si hay datos en el buffer, usarlos primero
        if (strlen($this->readBuffer) >= $length) {
            $data = substr($this->readBuffer, 0, $length);
            $this->readBuffer = substr($this->readBuffer, $length);
            return $data;
        }

        // Leer datos del socket
        $chunk = $this->recv($length);

        if ($chunk === false || $chunk === '') {
            return false;
        }

        $this->readBuffer .= $chunk;

        // Si ya tenemos suficientes datos, devolver
        if (strlen($this->readBuffer) >= $length) {
            $data = substr($this->readBuffer, 0, $length);
            $this->readBuffer = substr($this->readBuffer, $length);
            return $data;
        }

        // Seguir leyendo hasta completar
        return $this->recvAll($length);
    }

    protected function recvAll(int $length): string|false
    {
        // Usar el buffer primero
        $data = '';
        $needed = $length;

        if (strlen($this->readBuffer) > 0) {
            $takeFromBuffer = min(strlen($this->readBuffer), $needed);
            $data .= substr($this->readBuffer, 0, $takeFromBuffer);
            $this->readBuffer = substr($this->readBuffer, $takeFromBuffer);
            $needed -= $takeFromBuffer;
        }

        if ($needed <= 0) {
            return $data;
        }

        // Leer el resto del socket
        while ($needed > 0) {
            $chunk = $this->recv($needed);

            if ($chunk === false || $chunk === '') {
                return false;
            }

            $data .= $chunk;
            $needed -= strlen($chunk);

            // Si recibimos más de lo necesario, guardar el exceso
            if ($needed < 0) {
                $this->readBuffer = substr($data, $length);
                $data = substr($data, 0, $length);
                break;
            }
        }

        return $data;
    }


    /**
     * Recibe una respuesta JSON completa
     */
    protected function receiveJson(): array
    {
        $data = <<<'EOF'
EOF;
        $depth = 0;
        $inString = false;
        $escape = false;

        do {
            $char = $this->recv(1);

            if ($char === false || $char === '') {
                break;
            }

            $data .= $char;

            if (!$inString) {
                if ($char === '{' || $char === '[') {
                    $depth++;
                } elseif ($char === '}' || $char === ']') {
                    $depth--;
                }
            }

            if ($char === '"' && !$escape) {
                $inString = !$inString;
            }

            $escape = $char === '\\' && !$escape;

        } while ($depth > 0);

        $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException('Respuesta JSON inválida: ' . $data);
        }

        return $decoded;
    }

    /**
     * Asegura que la conexión está establecida
     */
    protected function ensureConnected(): void
    {
        if (!$this->isConnected()) {
            $connected = $this->connect($this->serverCfg->host, $this->serverCfg->port);

            if (!$connected) {
                throw new RuntimeException(
                    sprintf('No se pudo conectar a %s:%s', $this->serverCfg->host, $this->serverCfg->port)
                );
            }
            $this->set([
                'timeout' => 60,       // 60 segundos para recv()
                'keep_alive' => true,  // Mantener conexión viva
            ]);
        }
    }

    public function getTargetHost(): string
    {
        return $this->serverCfg->host;
    }
}