<?php

declare(strict_types=1);

namespace Tabula17\Satelles\Nexus\Utilis\Protocol;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\System;
use Swoole\Http\Response;
use Swoole\Server;
use Tabula17\Satelles\Utilis\File\MimeTypes;
use Tabula17\Satelles\Utilis\Job\AbstractJobResult;

abstract class AbstractFileJobResult extends AbstractJobResult implements FileJobResultInterface
{
    // Constantes para el protocolo de framing
    final protected const int FRAME_TYPE_DATA = 0x01;
    final protected const int FRAME_TYPE_PROGRESS = 0x02;
    final protected const int FRAME_TYPE_ERROR = 0x03;
    final protected const int FRAME_TYPE_HEADER = 0x04;
    final protected const int FRAME_TYPE_END = 0x05;

    public readonly bool $success;
    public readonly ?string $outputPath;
    public readonly ?string $base64Content;
    public readonly ?string $errorMessage;

    // ==================== MÉTODOS PÚBLICOS ====================

    public function isStream(): bool
    {
        return $this->base64Content !== null && $this->base64Content !== '';
    }

    public function isFile(): bool
    {
        return $this->outputPath !== null && $this->outputPath !== '';
    }

    public function hasError(): bool
    {
        return !$this->success && $this->errorMessage !== null && $this->errorMessage !== '';
    }

    // ==================== LECTURA DE ARCHIVOS ====================

    public function getFileContent(bool $useCoroutine = true): ?string
    {
        if ($this->isStream() && !$this->isFile()) {
            return base64_decode($this->base64Content);
        }

        if ($this->isFile() && $this->outputPath !== null) {
            $inCoroutine = Coroutine::getCid() > 0;
            $fileSize = filesize($this->outputPath);

            if ($fileSize > 50 * 1024 * 1024) {
                trigger_error("Archivo grande ({$fileSize} bytes). Considera usar getFileStream() o streamToFile()", E_USER_WARNING);
            }

            if ($useCoroutine && $inCoroutine && class_exists(System::class)) {
                $content = System::readFile($this->outputPath);
                return $content !== false ? $content : null;
            }

            if (file_exists($this->outputPath)) {
                $memoryLimit = $this->getMemoryLimit();
                if ($fileSize > $memoryLimit * 0.5) {
                    trigger_error("Archivo ({$fileSize} bytes) excede el 50% del memory_limit ({$memoryLimit} bytes)", E_USER_WARNING);
                }
                return file_get_contents($this->outputPath);
            }
        }

        return null;
    }

    public function getStream(bool $useCoroutine = true): ?string
    {
        if ($this->isStream()) {
            return $this->base64Content;
        }
        $content = $this->getFileContent($useCoroutine);
        return $content !== null ? base64_encode($content) : null;
    }

    public function writeFile(string $path, bool $useCoroutine = true): int|false
    {
        $content = $this->getFileContent($useCoroutine);
        if ($content === null) return false;

        $inCoroutine = Coroutine::getCid() > 0;
        if ($useCoroutine && $inCoroutine && class_exists(System::class)) {
            return System::writeFile($path, $content);
        }
        return file_put_contents($path, $content) !== false ? strlen($content) : false;
    }

    public function streamToFile(string $path, int $chunkSize = 1048576): int|false
    {
        $destination = fopen($path, 'wb');
        if ($destination === false) {
            return false;
        }

        $totalBytes = 0;
        try {
            if ($this->isStream() && !$this->isFile()) {
                $decoded = base64_decode($this->base64Content);
                $totalBytes = fwrite($destination, $decoded);
                return $totalBytes;
            }

            if ($this->isFile()) {
                $source = fopen($this->outputPath, 'rb');
                if ($source === false) return false;

                try {
                    while (!feof($source)) {
                        $chunk = fread($source, $chunkSize);
                        $written = fwrite($destination, $chunk);
                        if ($written === false) return false;
                        $totalBytes += $written;
                        if (Coroutine::getCid() > 0) {
                            Coroutine::sleep(0.001);
                        }
                    }
                } finally {
                    fclose($source);
                }
            }
            return $totalBytes;
        } finally {
            fclose($destination);
        }
        return false;
    }

    public function getFileStream(int $chunkSize = 1048576): ?\Generator
    {
        if ($this->isStream()) {
            $stream = fopen('php://temp', 'rb+');
            fwrite($stream, base64_decode($this->base64Content));
            rewind($stream);
            while (!feof($stream)) yield fread($stream, $chunkSize);
            fclose($stream);
            return null;
        }

        if ($this->isFile() && $this->outputPath !== null) {
            $handle = fopen($this->outputPath, 'rb');
            if ($handle === false) return null;
            try {
                while (!feof($handle)) yield fread($handle, $chunkSize);
            } finally {
                fclose($handle);
            }
        }
        return null;
    }

    // ==================== ENVÍO HTTP ====================

    public function streamToHttpResponse(Response $response, ?string $fileName = null, int $chunkSize = 1048576): void
    {
        $ext = pathinfo($fileName ?? '', PATHINFO_EXTENSION);
        $mime = MimeTypes::fromExtension($ext)->mime() ?? 'application/octet-stream';

        $response->header('Content-Type', $mime);
        if ($fileName) {
            $response->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        }

        if ($this->isFile() && $this->outputPath !== null) {
            if (function_exists('swoole_sendfile') && Coroutine::getCid() > 0) {
                $response->sendfile($this->outputPath);
                return;
            }
            $handle = fopen($this->outputPath, 'rb');
            if ($handle === false) {
                $response->status(500);
                $response->end('Error al abrir archivo');
                return;
            }
            try {
                while (!feof($handle)) $response->write(fread($handle, $chunkSize));
            } finally {
                fclose($handle);
            }
            $response->end();
            return;
        }

        if ($this->isStream()) {
            $response->end(base64_decode($this->base64Content));
            return;
        }

        $response->status(404);
        $response->end('No hay contenido disponible');
    }

    // ==================== ENVÍO TCP (UNIFICADO CON FRAMING) ====================

    /**
     * Envía el archivo por TCP usando framing (protocolo robusto)
     */
    public function streamToTcp(Server $server, int $fd, int $chunkSize = 1048576): bool
    {
        return $this->streamToTcpWithProgress($server, $fd, false, $chunkSize);
    }

    /**
     * Envía el archivo por TCP con framing y progreso opcional
     *
     * Protocolo:
     * [0x04][HEADER JSON]         ← Metadatos
     * [0x02][PROGRESS JSON]       ← Opcional, cada 10%
     * [0x01][CHUNK DATA]          ← Datos del archivo
     * [0x05][END JSON]            ← Finalización
     */
    public function streamToTcpWithProgress(
        Server $server,
        int $fd,
        bool $sendProgress = true,
        int $chunkSize = 1048576
    ): bool {
        // ✅ CORREGIDO: Manejar tanto archivo como base64
        if ($this->isStream() && !$this->isFile()) {
            return $this->streamBase64WithFraming($server, $fd, $sendProgress, $chunkSize);
        }

        if (!$this->isFile() || $this->outputPath === null) {
            $this->sendFrame($server, $fd, self::FRAME_TYPE_ERROR, json_encode(['error' => 'No content available']));
            return false;
        }

        if (!file_exists($this->outputPath)) {
            $this->sendFrame($server, $fd, self::FRAME_TYPE_ERROR, json_encode(['error' => 'File not found']));
            return false;
        }

        $fileSize = filesize($this->outputPath);
        $handle = fopen($this->outputPath, 'rb');
        if ($handle === false) {
            $this->sendFrame($server, $fd, self::FRAME_TYPE_ERROR, json_encode(['error' => 'Cannot open file']));
            return false;
        }

        try {
            // Enviar HEADER
            $this->sendFrame($server, $fd, self::FRAME_TYPE_HEADER, json_encode([
                'jobId' => $this->jobId,
                'size' => $fileSize,
                'hasProgress' => $sendProgress
            ]));

            $sentBytes = 0;
            $lastProgress = -1;
            $inCoroutine = Coroutine::getCid() > 0;

            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    $this->sendFrame($server, $fd, self::FRAME_TYPE_ERROR, json_encode(['error' => 'Read error']));
                    return false;
                }

                $chunkLength = strlen($chunk);
                $sentBytes += $chunkLength;

                // Enviar PROGRESO (opcional)
                if ($sendProgress) {
                    $currentProgress = (int)(($sentBytes / $fileSize) * 100);
                    if ($currentProgress > $lastProgress && $currentProgress % 10 === 0) {
                        $this->sendFrame($server, $fd, self::FRAME_TYPE_PROGRESS, json_encode([
                            'progress' => $currentProgress,
                            'sent' => $sentBytes,
                            'total' => $fileSize
                        ]));
                        $lastProgress = $currentProgress;
                    }
                }

                // Enviar DATOS
                $this->sendFrame($server, $fd, self::FRAME_TYPE_DATA, $chunk);

                if ($inCoroutine && $sentBytes % ($chunkSize * 5) === 0) {
                    Coroutine::sleep(0.001);
                }
            }

            // Enviar END
            $this->sendFrame($server, $fd, self::FRAME_TYPE_END, json_encode([
                'totalSent' => $sentBytes,
                'success' => $sentBytes === $fileSize
            ]));

            return $sentBytes === $fileSize;

        } finally {
            fclose($handle);
        }
    }

    /**
     * Envía contenido base64 con framing
     */
    private function streamBase64WithFraming(
        Server $server,
        int $fd,
        bool $sendProgress,
        int $chunkSize
    ): bool {
        $decoded = base64_decode($this->base64Content);
        $totalSize = strlen($decoded);

        // Enviar HEADER
        $this->sendFrame($server, $fd, self::FRAME_TYPE_HEADER, json_encode([
            'jobId' => $this->jobId,
            'size' => $totalSize,
            'hasProgress' => $sendProgress
        ]));

        $offset = 0;
        $inCoroutine = Coroutine::getCid() > 0;

        while ($offset < $totalSize) {
            $chunk = substr($decoded, $offset, $chunkSize);
            $chunkLen = strlen($chunk);
            $offset += $chunkLen;

            // Enviar DATOS
            $this->sendFrame($server, $fd, self::FRAME_TYPE_DATA, $chunk);

            if ($inCoroutine && $offset % ($chunkSize * 5) === 0) {
                Coroutine::sleep(0.001);
            }
        }

        // Enviar END
        $this->sendFrame($server, $fd, self::FRAME_TYPE_END, json_encode([
            'totalSent' => $totalSize,
            'success' => true
        ]));

        return true;
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function sendFrame(Server $server, int $fd, int $type, string $data): void
    {
        $server->send($fd, chr($type) . pack('N', strlen($data)) . $data);
    }

    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return PHP_INT_MAX;

        $unit = strtoupper(substr($limit, -1));
        $value = (int)substr($limit, 0, -1);

        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => (int)$limit,
        };
    }

    // ==================== CHANNEL ====================

    public function streamToChannel(Channel $channel, int $chunkSize = 1048576): bool
    {
        if ($this->isFile() && $this->outputPath !== null) {
            $handle = fopen($this->outputPath, 'rb');
            if ($handle === false) {
                $channel->push(['type' => 'error', 'message' => 'Cannot open file']);
                return false;
            }
            try {
                $channel->push(['type' => 'header', 'jobId' => $this->jobId, 'size' => filesize($this->outputPath)]);
                while (!feof($handle)) $channel->push(['type' => 'data', 'chunk' => fread($handle, $chunkSize)]);
                $channel->push(['type' => 'end']);
                return true;
            } finally {
                fclose($handle);
            }
        }

        if ($this->isStream()) {
            $decoded = base64_decode($this->base64Content);
            $channel->push(['type' => 'header', 'jobId' => $this->jobId, 'size' => strlen($decoded)]);
            $channel->push(['type' => 'data', 'chunk' => $decoded]);
            $channel->push(['type' => 'end']);
            return true;
        }

        $channel->push(['type' => 'error', 'message' => 'No content available']);
        return false;
    }

    abstract public function validate(): void;
}