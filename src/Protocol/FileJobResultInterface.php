<?php

namespace Tabula17\Satelles\Nexus\Utilis\Protocol;

use Generator;
use Swoole\Coroutine\Channel;
use Swoole\Http\Response;
use Swoole\Server;
use Tabula17\Satelles\Utilis\Job\JobResultInterface;

interface FileJobResultInterface extends JobResultInterface
{
    public function isStream(): bool;

    public function isFile(): bool;

    public function hasError(): bool;

    /**
     * Obtiene el contenido del archivo (carga completa en memoria)
     * ADVERTENCIA: Para archivos grandes, usar getFileStream() o streamToFile()
     *
     * @param bool $useCoroutine Si es true, usa Swoole\Coroutine\System::readFile()
     * @return string|null
     */
    public function getFileContent(bool $useCoroutine = true): ?string;

    public function getStream(bool $useCoroutine = true): ?string;

    public function writeFile(string $path, bool $useCoroutine = true): int|false;

    public function streamToFile(string $path, int $chunkSize = 1048576): int|false;

    /**
     * Obtiene el contenido del archivo como un generador de chunks
     *
     * @param int $chunkSize Tamaño del chunk en bytes
     * @return Generator<string>|null
     */
    public function getFileStream(int $chunkSize = 1048576): ?Generator;

    public function streamToHttpResponse(Response $response, ?string $fileName = null, int $chunkSize = 1048576): void;

    /**
     * Envía el archivo a través de una conexión TCP usando streaming
     */
    public function streamToTcp(Server $server, int $fd, int $chunkSize = 1048576): bool;

    /**
     * Envía el archivo con actualizaciones de progreso usando framing
     */
    public function streamToTcpWithProgress(Server $server, int $fd, bool $sendProgress = true, int $chunkSize = 1048576): bool;

    /**
     * Envía el archivo a un Channel de Swoole
     */
    public function streamToChannel(Channel $channel, int $chunkSize = 1048576): bool;

}