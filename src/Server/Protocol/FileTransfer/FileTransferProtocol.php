<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer;

use Psr\Log\LoggerInterface;
use Swoole\Server;
use Swoole\Table;
use Swoole\Timer;
use Tabula17\Satelles\Nexus\Utilis\Exception\RuntimeException;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumTypes;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Action;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ServiceProtocol;
use Throwable;

class FileTransferProtocol implements ProtocolManagerInterface
{
    // Comandos del protocolo
    const int CMD_INIT_TRANSFER = 0x01;
    const int CMD_DATA_CHUNK = 0x02;
    const int CMD_TRANSFER_COMPLETE = 0x03;
    const int CMD_TRANSFER_ERROR = 0x04;
    const int CMD_REQUEST_RESEND = 0x05;

    // Tamaño máximo de chunk para no saturar el buffer (64KB)
    const int CHUNK_SIZE = 65536;

    private array $activeTransfers = [];
    protected array $completeCallbacks = [];
    protected CallbacksCollection $receiveCallbacks;
    protected readonly string $storagePath;

    private Table $transferState;
    private Table $transferMetadata;

    /**
     * @throws RuntimeException
     */
    public function __construct(string $storagePath = '/tmp/file_transfers', protected readonly ?LoggerInterface $logger = null)
    {
        //$this->server = $server;
        $this->storagePath = $storagePath;

        if (!is_dir($storagePath) && !mkdir($storagePath, 0755, true) && !is_dir($storagePath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $storagePath));
        }
        if (!is_writable($storagePath)) {
            throw new RuntimeException(sprintf('Directory "%s" is not writable', $storagePath));
        }
        $this->receiveCallbacks = new CallbacksCollection();
        $this->initTables();

        //$this->registerProtocolHandlers(); // se ejecuta cuando el servidor se inicia (MatrixTrait->addProtocolManager)
    }

    public function registerProtocolHandlers(HamumServerInterface $server): void
    {
        $protocol = $this->getProtocolName();
        if ($server::TYPE === HamumTypes::TCP) {
            // Registrar handlers para el protocolo de transferencia
            $server->registerReceiveHandlers(FileTransferActionsEnum::TransferInit->value, $this->handleInitTransfer(...), $protocol);
            $server->registerReceiveHandlers(FileTransferActionsEnum::TransferChunk->value, $this->handleDataChunk(...), $protocol);
            $server->registerReceiveHandlers(FileTransferActionsEnum::TransferComplete->value, $this->handleTransferComplete(...), $protocol);
            $server->registerReceiveHandlers(FileTransferActionsEnum::TransferResend->value, $this->handleRequestResend(...), $protocol);
        } else {
            $this->logger?->error("File Transfer Protocol not supported for {$server::TYPE->name}");
        }

        // Handlers para eventos de conexión
        // $this->server->registerConnectHandlers('file_transfer_connect', [$this, 'handleConnection'], 'file_transfer');
    }

    private function initTables(): void
    {
        // Tabla para estado numérico con operaciones atómicas
        $this->transferState = new Table(2048);
        $this->transferState->column('fd', Table::TYPE_INT);
        $this->transferState->column('file_size', Table::TYPE_INT);
        $this->transferState->column('chunks_sent', Table::TYPE_INT);
        $this->transferState->column('total_chunks', Table::TYPE_INT);
        $this->transferState->column('received_chunks', Table::TYPE_INT);
        $this->transferState->column('is_sender', Table::TYPE_INT);
        $this->transferState->column('worker_pid', Table::TYPE_INT);
        $this->transferState->column('has_error', Table::TYPE_INT);
        $this->transferState->create();

        // Tabla para metadatos de texto
        $this->transferMetadata = new Table(2048);
        $this->transferMetadata->column('file_path', Table::TYPE_STRING, 512);
        $this->transferMetadata->column('file_name', Table::TYPE_STRING, 256);
        $this->transferMetadata->column('temp_file', Table::TYPE_STRING, 512);
        $this->transferMetadata->column('error_message', Table::TYPE_STRING, 512);
        $this->transferMetadata->create();
    }

    public function registerReceiveCallback(TransferCompleteInterface $callback): void
    {
        $this->receiveCallbacks->add($callback);
    }

    /**
     * Registra una transferencia activa en memoria compartida
     */
    private function registerTransfer(string $transferId, array $data): void
    {

        $this->transferState->set($transferId, [
            'fd' => $data['fd'],
            'file_size' => $data['file_size'],
            'chunks_sent' => $data['chunks_sent'] ?? 0,
            'total_chunks' => $data['total_chunks'],
            'received_chunks' => $data['received_chunks'] ?? 0,
            'is_sender' => $data['is_sender'] ? 1 : 0,
            'worker_pid' => getmypid(),
            'has_error' => 0,
        ]);

        $this->transferMetadata->set($transferId, [
            'file_path' => $data['file_path'] ?? '',
            'file_name' => $data['file_name'],
            'temp_file' => $data['temp_file'] ?? '',
            'error_message' => '',
        ]);
    }

    protected function getActiveTransfer(string $transferId): ?array
    {
        $state = $this->transferState->get($transferId);
        if (!$state) {
            return null;
        }

        $metadata = $this->transferMetadata->get($transferId);

        return [
            'fd' => $state['fd'],
            'file_path' => $metadata['file_path'],
            'file_name' => $metadata['file_name'],
            'file_size' => $state['file_size'],
            'chunks_sent' => $state['chunks_sent'],
            'total_chunks' => $state['total_chunks'],
            'received_chunks' => $state['received_chunks'],
            'is_sender' => (bool)$state['is_sender'],
            'worker_pid' => $state['worker_pid'],
            'temp_file' => $metadata['temp_file'],
            'has_error' => (bool)$state['has_error'],
            'error_message' => $metadata['error_message'],
        ];
    }

    /**
     * Elimina una transferencia de la memoria compartida
     */
    private function removeTransfer(string $transferId): void
    {
        $this->transferState->del($transferId);
        $this->transferMetadata->del($transferId);
    }

    /**
     * Inicia una transferencia de archivo desde el servidor al cliente
     */
    public function sendFile(Server $server, int $fd, string $filePath, ?string $fileName = null, ?CallbacksCollection $onComplete = null): bool
    {
        if (!file_exists($filePath)) {
            $this->logger?->error("File not found: {$filePath}");
            return false;
        }

        $fileName = $fileName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $transferId = uniqid('transfer_', true);

        // Almacenar información de la transferencia
        $this->activeTransfers[$transferId] = [
            'fd' => $fd,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'chunks_sent' => 0,
            'total_chunks' => ceil($fileSize / self::CHUNK_SIZE),
            'start_time' => microtime(true),
            'resend_requests' => [],
            'is_sender' => true
        ];
        $this->logger?->info("Initiated send transfer {$transferId}: {$fileName} ({$fileSize} bytes)");
        // Agregar callbacks para manejar errores y completar la transferencia asociados al transferId
        // De esta forma cada llamada a sendFile() puede tener su propia lógica de error y completado
        if ($onComplete) {
            $this->completeCallbacks[$transferId] = $onComplete;
        }
        // Enviar comando de inicio
        $initPacket = $this->createPacket(self::CMD_INIT_TRANSFER, [
            'transfer_id' => $transferId,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'chunk_size' => self::CHUNK_SIZE,
            'total_chunks' => $this->activeTransfers[$transferId]['total_chunks']
        ]);

        $server->send($fd, $initPacket);

        // Iniciar envío de chunks de manera asíncrona
        $this->sendNextChunks($server, $transferId);

        return true;
    }

    /**
     * Envía los siguientes chunks pendientes de manera asíncrona
     */
    private function sendNextChunks(Server $server, string $transferId): void
    {
        if (!isset($this->activeTransfers[$transferId])) {
            return;
        }

        $transfer = &$this->activeTransfers[$transferId];

        // Enviar hasta 10 chunks por ciclo para no bloquear
        $chunksToSend = min(10, $transfer['total_chunks'] - $transfer['chunks_sent']);

        for ($i = 0; $i < $chunksToSend; $i++) {
            $chunkIndex = $transfer['chunks_sent'];

            if ($chunkIndex >= $transfer['total_chunks']) {
                break;
            }

            $offset = $chunkIndex * self::CHUNK_SIZE;
            $chunkData = file_get_contents($transfer['file_path'], false, null, $offset, self::CHUNK_SIZE);

            if ($chunkData === false) {
                $this->handleError($server, $transferId, "Failed to read chunk {$chunkIndex}");
                return;
            }

            $chunkPacket = $this->createPacket(self::CMD_DATA_CHUNK, [
                'transfer_id' => $transferId,
                'chunk_index' => $chunkIndex,
                'data' => base64_encode($chunkData)
            ]);

            $server->send($transfer['fd'], $chunkPacket);
            $transfer['chunks_sent']++;

            $this->logger?->debug("Sent chunk {$chunkIndex}/{$transfer['total_chunks']} for transfer {$transferId}");
        }

        // Si quedan chunks, programar el siguiente envío
        if ($transfer['chunks_sent'] < $transfer['total_chunks']) {
            // Usar timer de Swoole para no bloquear el event loop
            Timer::after(10, function () use ($server, $transferId) {
                $this->sendNextChunks($server, $transferId);
            });
        } else {
            // Todos los chunks enviados, enviar comando de completado
            $completePacket = $this->createPacket(self::CMD_TRANSFER_COMPLETE, [
                'transfer_id' => $transferId,
                'total_chunks' => $transfer['total_chunks'],
                'transfer_time' => microtime(true) - $transfer['start_time']
            ]);

            $server->send($transfer['fd'], $completePacket);
            $this->logger?->info("Transfer {$transferId} completed in " . (microtime(true) - $transfer['start_time']) . "s");
        }
    }

    /**
     * Maneja la recepción de datos del cliente
     */
    public function handleInitTransfer(Server $server, int $fd, int $reactorId, string $data): void
    {
        $packet = json_decode($data, true);
        $transferId = $packet['transfer_id'];

        // Registrar en memoria compartida para que todos los workers la conozcan
        $this->registerTransfer($transferId, [
            'fd' => $fd,
            'file_name' => $packet['file_name'],
            'file_size' => $packet['file_size'],
            'total_chunks' => $packet['total_chunks'],
            'chunk_size' => $packet['chunk_size'],
            'received_chunks' => 0,
            'is_sender' => false
        ]);

        // Crear archivo temporal (cada worker escribe en el mismo archivo compartido)
        $tempFile = $this->storagePath . '/' . $transferId . '.part';
        $this->transferMetadata->set($transferId, [
            'file_path' => '',
            'file_name' => $packet['file_name'],
            'temp_file' => $tempFile,
        ]);

        $this->logger?->info("Initiated receive transfer {$transferId}: {$packet['file_name']} ({$packet['file_size']} bytes)");
    }

    public function handleDataChunk(Server $server, int $fd, int $reactorId, string $data): void
    {
        $packet = json_decode($data, true);
        $transferId = $packet['transfer_id'];

        // Obtener de memoria compartida
        $transfer = $this->getActiveTransfer($transferId);
        if (!$transfer) {
            $this->logger?->error("Unknown transfer: {$transferId}");
            return;
        }

        $chunkData = base64_decode($packet['data']);
        $chunkIndex = $packet['chunk_index'];

        // Archivo temporal compartido entre workers
        $tempFile = $transfer['temp_file'];

        // Usar flock para evitar corrupción cuando múltiples workers escriben
        $fileHandle = fopen($tempFile, 'cb');
        if ($fileHandle && flock($fileHandle, LOCK_EX)) {
            $offset = $chunkIndex * self::CHUNK_SIZE;
            fseek($fileHandle, $offset);
            fwrite($fileHandle, $chunkData);
            fflush($fileHandle);
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);
        }

        // Incrementar contador de chunks recibidos (operación atómica)
        $this->transferState->incr($transferId, 'received_chunks');

        $this->logger?->debug("Received chunk {$chunkIndex}/{$transfer['total_chunks']} for transfer {$transferId}");
    }

    public function handleTransferComplete(Server $server, int $fd, int $reactorId, string $data): void
    {
        $packet = json_decode($data, true);
        $transferId = $packet['transfer_id'];

        $transfer = $this->getActiveTransfer($transferId);
        if (!$transfer) {
            $this->logger?->warning("TransferComplete received for unknown transfer: {$transferId}");
            return;
        }

        // Verificar que no tenga error previo
        if ($transfer['has_error']) {
            $this->logger?->warning("Transfer {$transferId} already had error: {$transfer['error_message']}");
            $this->removeTransfer($transferId);
            return;
        }

        if (!$transfer['is_sender']) {
            // RECEPTOR: Verificar integridad del archivo
            $tempFile = $transfer['temp_file'];

            if (!file_exists($tempFile)) {
                $this->handleError($server, $transferId, "Temp file not found: {$tempFile}");
                return;
            }

            $receivedFileSize = filesize($tempFile);
            $expectedSize = $transfer['file_size'];

            if ($receivedFileSize === $expectedSize) {
                // Éxito: verificar que todos los chunks fueron recibidos
                $receivedChunks = $transfer['received_chunks'];
                $totalChunks = $transfer['total_chunks'];

                if ($receivedChunks < $totalChunks) {
                    // Faltan chunks, solicitar reenvío automáticamente
                    $this->logger?->warning("Transfer {$transferId} marked complete but missing chunks: {$receivedChunks}/{$totalChunks}");

                    $missingChunks = $this->findMissingChunks($transferId, $totalChunks, $receivedChunks);
                    if (!empty($missingChunks)) {
                        $this->requestChunkResend($server, $transferId, $fd, $missingChunks);
                        return; // No finalizar aún, esperar reenvíos
                    }
                }

                // Archivo íntegro y completo
                $finalPath = $this->storagePath . '/' . $transfer['file_name'];

                // Si ya existe, agregar sufijo único
                if (file_exists($finalPath)) {
                    $finalPath = $this->storagePath . "/" . pathinfo($transfer['file_name'], PATHINFO_FILENAME) . "_" . uniqid() . "." . pathinfo($transfer['file_name'], PATHINFO_EXTENSION);
                }

                rename($tempFile, $finalPath);

                $transferTime = microtime(true) - $this->getTransferStartTime($transferId);
                $this->logger?->info(
                    "Transfer {$transferId} completed successfully: {$transfer['file_name']} " .
                    "({$receivedFileSize} bytes) in {$transferTime}s"
                );

                // Notificar finalización exitosa
                $this->onTransferComplete($transferId, $finalPath, true);
            } else {
                // Tamaño no coincide
                $this->handleError(
                    $server,
                    $transferId,
                    "File size mismatch: expected {$expectedSize}, got {$receivedFileSize}"
                );
                return;
            }
        } else {
            // EMISOR: La transferencia se completó del lado del cliente
            $transferTime = microtime(true) - $this->getTransferStartTime($transferId);
            $this->logger?->info(
                "Transfer {$transferId} acknowledged complete by client in {$transferTime}s"
            );

            // Notificar finalización exitosa
            $this->onTransferComplete($transferId, $transfer['file_path'], true);
        }

        // Limpiar memoria compartida
        $this->removeTransfer($transferId);
    }

    public function handleRequestResend(Server $server, int $fd, int $reactorId, string $data): void
    {
        $packet = json_decode($data, true);
        $transferId = $packet['transfer_id'];
        $missingChunks = $packet['missing_chunks'] ?? [];

        $transfer = $this->getActiveTransfer($transferId);
        if (!$transfer) {
            $this->logger?->warning("Resend request for unknown transfer: {$transferId}");
            return;
        }

        if (!$transfer['is_sender']) {
            $this->logger?->warning("Resend request received but this worker is not sender for transfer {$transferId}");
            return;
        }

        if (empty($missingChunks)) {
            $this->logger?->warning("Empty missing chunks list for transfer {$transferId}");
            return;
        }

        $this->logger?->info(
            "Resending " . count($missingChunks) . " chunks for transfer {$transferId}: " .
            implode(', ', array_slice($missingChunks, 0, 10)) .
            (count($missingChunks) > 10 ? '...' : '')
        );

        foreach ($missingChunks as $chunkIndex) {
            $offset = $chunkIndex * self::CHUNK_SIZE;
            $chunkData = @file_get_contents($transfer['file_path'], false, null, $offset, self::CHUNK_SIZE);

            if ($chunkData === false) {
                $this->handleError(
                    $server,
                    $transferId,
                    "Failed to read chunk {$chunkIndex} for resend"
                );
                return;
            }

            $chunkPacket = $this->createPacket(self::CMD_DATA_CHUNK, [
                'transfer_id' => $transferId,
                'chunk_index' => $chunkIndex,
                'data' => base64_encode($chunkData)
            ]);

            $server->send($transfer['fd'], $chunkPacket);
            $this->logger?->debug("Resent chunk {$chunkIndex} for transfer {$transferId}");
        }
    }

    private function createPacket(int $command, array $data): string
    {
        $data['command'] = $command;
        return json_encode($data);
    }

    private function handleError(Server $server, string $transferId, string $message): void
    {
        $this->logger?->error("Transfer {$transferId} error: {$message}");

        $transfer = $this->getActiveTransfer($transferId);
        if (!$transfer) {
            return;
        }

        // Marcar error en la tabla compartida
        $this->transferState->set($transferId, [
            'fd' => $transfer['fd'],
            'file_size' => $transfer['file_size'],
            'chunks_sent' => $transfer['chunks_sent'],
            'total_chunks' => $transfer['total_chunks'],
            'received_chunks' => $transfer['received_chunks'],
            'is_sender' => $transfer['is_sender'] ? 1 : 0,
            'worker_pid' => $transfer['worker_pid'],
            'has_error' => 1,
        ]);

        $this->transferMetadata->set($transferId, [
            'file_path' => $transfer['file_path'],
            'file_name' => $transfer['file_name'],
            'temp_file' => $transfer['temp_file'],
            'error_message' => $message,
        ]);

        // Enviar notificación de error al cliente
        $errorPacket = $this->createPacket(self::CMD_TRANSFER_ERROR, [
            'transfer_id' => $transferId,
            'error' => $message
        ]);

        // Solo enviar si el fd es válido y pertenece a este worker o al worker original
        $server->send($transfer['fd'], $errorPacket);

        // Notificar callbacks de error
        $this->onTransferComplete($transferId, '', false);

        // Limpiar recursos
        if (!$transfer['is_sender'] && !empty($transfer['temp_file'])) {
            @unlink($transfer['temp_file']);
        }

        $this->removeTransfer($transferId);
    }

    private function onTransferComplete(string $transferId, string $finalPath, bool $success): void
    {$transfer = $this->getActiveTransfer($transferId);
        $isSender = $transfer ? $transfer['is_sender'] : false;

        if ($isSender) {
            // Callbacks específicos para esta transferencia de envío
            if (isset($this->sendCompleteCallbacks[$transferId])) {
                $callbacks = $this->sendCompleteCallbacks[$transferId];
                unset($this->sendCompleteCallbacks[$transferId]);

                foreach ($callbacks as $callback) {
                    try {
                        $callback($transferId, $finalPath, $success);
                    } catch (Throwable $e) {
                        $this->logger?->error(
                            "Error in send complete callback for transfer {$transferId}: {$e->getMessage()}"
                        );
                    }
                }
            }
        } else {
            // Callbacks generales de recepción
            foreach ($this->receiveCallbacks as $callback) {
                try {
                    $callback($transferId, $finalPath, $success);
                } catch (Throwable $e) {
                    $this->logger?->error(
                        "Error in receive complete callback for transfer {$transferId}: {$e->getMessage()}"
                    );
                }
            }
        }

        // Si hubo error y es receptor, limpiar archivo temporal
        if (!$success && !$isSender) {
            $tempFile = $transfer['temp_file'] ?? null;
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
                $this->logger?->debug("Cleaned up temp file for failed transfer {$transferId}: {$tempFile}");
            }
        }
    }
    /**
     * Encuentra chunks faltantes analizando el archivo temporal
     */
    private function findMissingChunks(string $transferId, int $totalChunks, int $receivedChunks): array
    {
        $transfer = $this->getActiveTransfer($transferId);
        if (!$transfer || empty($transfer['temp_file'])) {
            return [];
        }

        $tempFile = $transfer['temp_file'];
        $missingChunks = [];

        for ($i = 0; $i < $totalChunks; $i++) {
            $offset = $i * self::CHUNK_SIZE;
            $expectedSize = ($i === $totalChunks - 1)
                ? $transfer['file_size'] - $offset
                : self::CHUNK_SIZE;

            // Verificar si el chunk existe y tiene el tamaño correcto
            $chunkExists = false;
            if (file_exists($tempFile)) {
                clearstatcache(true, $tempFile);
                $fileSize = filesize($tempFile);

                if ($offset < $fileSize) {
                    $chunkActualSize = min($expectedSize, $fileSize - $offset);
                    // Verificación básica: el archivo debe tener al menos hasta este offset
                    $chunkExists = ($offset + $chunkActualSize <= $fileSize);
                }
            }

            if (!$chunkExists) {
                $missingChunks[] = $i;
            }
        }

        return $missingChunks;
    }

    /**
     * Solicita reenvío de chunks específicos
     */
    private function requestChunkResend(Server $server, string $transferId, int $fd, array $missingChunks): void
    {
        $resendPacket = $this->createPacket(self::CMD_REQUEST_RESEND, [
            'transfer_id' => $transferId,
            'missing_chunks' => $missingChunks
        ]);

        $server->send($fd, $resendPacket);

        $this->logger?->info(
            "Requested resend of " . count($missingChunks) . " chunks for transfer {$transferId}"
        );
    }

    /**
     * Obtiene el tiempo de inicio de una transferencia (almacenado aparte)
     */
    private function getTransferStartTime(string $transferId): float
    {
        // Como Table no soporta float fácilmente, podrías:
        // Opción 1: Agregar columna start_time como INT (microtime * 1000000)
        // Opción 2: Usar el timestamp del archivo temporal
        $transfer = $this->getActiveTransfer($transferId);
        if ($transfer && !empty($transfer['temp_file']) && file_exists($transfer['temp_file'])) {
            return (float)filectime($transfer['temp_file']);
        }
        return microtime(true);
    }

    public Action $definition;

    public function initializeOnStart(HamumServerInterface $server): void
    {
        // TODO: Implement initializeOnStart() method.
    }

    public function initializeOnWorkers(HamumServerInterface $server, int $workerId): void
    {
        // TODO: Implement initializeOnWorkers() method.
    }

    public function runOnOpenConnection(...$args): void
    {
        // TODO: Implement runOnOpenConnection() method.
    }

    public function runOnCloseConnection(HamumServerInterface $server, int $fd, int $reactorId): void
    {
        // TODO: Implement runOnCloseConnection() method.
    }

    public function cleanUpResources(HamumServerInterface $server, int $fd = 0): void
    {
        $this->logger?->debug("Cleaning up resources for FD {$fd}");
    }

    public function getProtocol(): ServiceProtocol
    {
        return ServiceProtocol::TCPFILE;
    }

    public function getProtocolName(): string
    {
        return $this->getProtocol()->shortName();
    }
}