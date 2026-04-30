<?php

namespace Tabula17\Satelles\Nexus\Utilis\Protocol;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\System;
use Swoole\Http\Response;
use Swoole\Server;
use Tabula17\Satelles\Nexus\Utilis\Server\MimeTypes;
use Tabula17\Satelles\Utilis\Job\AbstractJobResult;

abstract class AbstractFileJobResult extends AbstractJobResult implements FileJobResultInterface
{
    // Constantes para el protocolo de framing
    final protected const int FRAME_TYPE_DATA = 0x01;
    final protected const int FRAME_TYPE_PROGRESS = 0x02;
    final protected const int FRAME_TYPE_ERROR = 0x03;
    final protected const int FRAME_TYPE_HEADER = 0x04;
    final protected const int FRAME_TYPE_END = 0x05;

    //  Marcadores de tipo de respuesta
    final protected const int RESPONSE_TYPE_JSON = 0x00;
    final protected const int RESPONSE_TYPE_STREAM = 0x01;
    public readonly bool $success;
    public readonly ?string $outputPath;
    public readonly ?string $base64Content;
    public readonly ?string $errorMessage;

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

    public function getFileContent(bool $useCoroutine = true): ?string
    {
        if ($this->isStream() && !$this->isFile()) {
            return base64_decode($this->base64Content);
        }

        if ($this->isFile() && $this->outputPath !== null) {
            $inCoroutine = Coroutine::getCid() > 0;

            $fileSize = filesize($this->outputPath);
            if ($fileSize > 50 * 1024 * 1024) {
                trigger_error(
                    "Archivo grande ({$fileSize} bytes). Considera usar getFileStream() o streamToFile()",
                    E_USER_WARNING
                );
            }

            if ($useCoroutine && $inCoroutine && class_exists(System::class)) {
                $content = System::readFile($this->outputPath);
                return $content !== false ? $content : null;
            }

            if (file_exists($this->outputPath)) {
                $memoryLimit = $this->getMemoryLimit();
                if ($fileSize > $memoryLimit * 0.5) {
                    trigger_error(
                        "Archivo ({$fileSize} bytes) excede el 50% del memory_limit ({$memoryLimit} bytes)",
                        E_USER_WARNING
                    );
                }
                return file_get_contents($this->outputPath);
            }
        }

        return null;
    }

    protected function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtoupper(substr($limit, -1));
        $value = (int)substr($limit, 0, -1);

        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => (int)$limit,
        };
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

        if ($content === null) {
            return false;
        }

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
                return fwrite($destination, $decoded);
            }

            if ($this->isFile()) {
                $source = fopen($this->outputPath, 'rb');

                if ($source === false) {
                    return false;
                }

                try {
                    while (!feof($source)) {
                        $chunk = fread($source, $chunkSize);
                        $written = fwrite($destination, $chunk);

                        if ($written === false) {
                            return false;
                        }

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

            while (!feof($stream)) {
                yield fread($stream, $chunkSize);
            }
            fclose($stream);
            return null;
        }

        if ($this->isFile() && $this->outputPath !== null) {
            $handle = fopen($this->outputPath, 'rb');

            if ($handle === false) {
                return null;
            }

            try {
                while (!feof($handle)) {
                    yield fread($handle, $chunkSize);
                }
            } finally {
                fclose($handle);
            }
        }

        return null;
    }

    public function streamToHttpResponse(Response $response, ?string $fileName = null, int $chunkSize = 1048576): void
    {
        $ext = pathinfo($fileName ?? '', PATHINFO_EXTENSION);
        $mime = MimeTypes::fromExtension($ext)->mime() ?? 'application/octet-stream';

        $response->header('Content-Type', $mime);

        if ($fileName) {
            $response->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        }

        if ($this->isFile()) {
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
                while (!feof($handle)) {
                    $chunk = fread($handle, $chunkSize);
                    $response->write($chunk);
                }
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

    public function streamToTcp(Server $server, int $fd, int $chunkSize = 1048576): bool
    {
        if ($this->isFile()) {
            return $this->streamFileToTcp($server, $fd, $this->outputPath, $chunkSize);
        }

        if ($this->isStream()) {
            return $this->streamBase64ToTcp($server, $fd, $this->base64Content, $chunkSize);
        }

        // ✅ Enviar error con marcador JSON
        $errorJson = json_encode(['error' => 'No content available']);
        $server->send($fd, chr(self::RESPONSE_TYPE_JSON) . $errorJson);
        return false;
    }
    public function streamToTcpWithProgress(Server $server, int $fd, bool $sendProgress = true, int $chunkSize = 1048576): bool
    {
        if (!$this->isFile() || $this->outputPath === null) {
            return $this->streamToTcp($server, $fd, $chunkSize);
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
            // ✅ El primer frame ya indica que es streaming con progreso
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

                $this->sendFrame($server, $fd, self::FRAME_TYPE_DATA, $chunk);

                if ($inCoroutine && $sentBytes % ($chunkSize * 5) === 0) {
                    Coroutine::sleep(0.001);
                }
            }

            $this->sendFrame($server, $fd, self::FRAME_TYPE_END, json_encode([
                'totalSent' => $sentBytes,
                'success' => $sentBytes === $fileSize
            ]));

            return $sentBytes === $fileSize;

        } finally {
            fclose($handle);
        }
    }

    private function streamFileToTcp(Server $server, int $fd, string $filePath, int $chunkSize): bool
    {
        if (!file_exists($filePath)) {
            // Enviar error como JSON con marcador 0x00
            $errorJson = json_encode(['error' => 'File not found']);
            $server->send($fd, chr(self::RESPONSE_TYPE_JSON) . $errorJson);
            return false;
        }

        $fileSize = filesize($filePath);
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            $errorJson = json_encode(['error' => 'Cannot open file']);
            $server->send($fd, chr(self::RESPONSE_TYPE_JSON) . $errorJson);
            return false;
        }

        try {
            // ✅ Enviar: [0x01][4 bytes: tamaño archivo][datos...]
            //$server->send($fd, chr(self::RESPONSE_TYPE_STREAM) . pack('N', $fileSize));
            $header = chr(0x01) . pack('N', $fileSize);
            echo "[SERVIDOR] Enviando header: " . bin2hex($header) . "\n";
            echo "[SERVIDOR] Primer byte (marcador): 0x" . bin2hex(chr(0x01)) . "\n";
            echo "[SERVIDOR] Tamaño: {$fileSize} -> 0x" . bin2hex(pack('N', $fileSize)) . "\n";
            $server->send($fd, $header);

            $sentBytes = 0;
            $inCoroutine = Coroutine::getCid() > 0;

            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);

                if ($chunk === false) {
                    return false;
                }

                $server->send($fd, $chunk);
                $sentBytes += strlen($chunk);

                if ($inCoroutine && $sentBytes % ($chunkSize * 5) === 0) {
                    Coroutine::sleep(0.001);
                }
            }

            return $sentBytes === $fileSize;

        } finally {
            fclose($handle);
        }
    }

    private function streamBase64ToTcp(Server $server, int $fd, string $base64Content, int $chunkSize): bool
    {
        $decoded = base64_decode($base64Content);
        $totalSize = strlen($decoded);

        // ✅ Enviar: [0x01][4 bytes: tamaño][datos...]
        $server->send($fd, chr(self::RESPONSE_TYPE_STREAM) . pack('N', $totalSize));

        $offset = 0;
        $inCoroutine = Coroutine::getCid() > 0;

        while ($offset < $totalSize) {
            $chunk = substr($decoded, $offset, $chunkSize);
            $server->send($fd, $chunk);
            $offset += $chunkSize;

            if ($inCoroutine && $offset % ($chunkSize * 5) === 0) {
                Coroutine::sleep(0.001);
            }
        }

        return true;
    }

    /**
     * Envía un frame con formato: [TIPO:1 byte][LONGITUD:4 bytes][DATOS]
     */
    private function sendFrame(Server $server, int $fd, int $type, string $data): void
    {
        $server->send($fd, chr($type) . pack('N', strlen($data)) . $data);
    }

    /**
     * Envía el archivo a un Channel de Swoole
     */
    public function streamToChannel(Channel $channel, int $chunkSize = 1048576): bool
    {
        if ($this->isFile()) {
            $handle = fopen($this->outputPath, 'rb');

            if ($handle === false) {
                $channel->push(['type' => 'error', 'message' => 'Cannot open file']);
                return false;
            }

            try {
                $channel->push([
                    'type' => 'header',
                    'jobId' => $this->jobId,
                    'size' => filesize($this->outputPath)
                ]);

                while (!feof($handle)) {
                    $chunk = fread($handle, $chunkSize);
                    $channel->push(['type' => 'data', 'chunk' => $chunk]);
                }

                $channel->push(['type' => 'end']);
                return true;

            } finally {
                fclose($handle);
            }
        }

        if ($this->isStream()) {
            $channel->push([
                'type' => 'header',
                'jobId' => $this->jobId,
                'size' => strlen(base64_decode($this->base64Content))
            ]);

            $channel->push(['type' => 'data', 'chunk' => base64_decode($this->base64Content)]);
            $channel->push(['type' => 'end']);
            return true;
        }

        $channel->push(['type' => 'error', 'message' => 'No content available']);
        return false;
    }

    abstract public function validate(): void;
}