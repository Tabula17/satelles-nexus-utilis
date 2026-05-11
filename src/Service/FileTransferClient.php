<?php

namespace Tabula17\Satelles\Nexus\Utilis\Service;

use Psr\Log\LoggerInterface;
use Swoole\Client;
use Swoole\Server;
use Swoole\Timer;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer\CallbacksCollection;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer\FileTransferActionsEnum;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer\FileTransferMetadata;

class FileTransferClient
{
    const int CMD_REQUEST_RESEND = 0x05;

    const int CHUNK_SIZE = 65536;

    private ?Client $client = null;
    private array $pendingTransfers = [];
    private array $sendCompleteCallbacks = [];
    private array $reconnectQueue = [];

    // Referencia al servidor Swoole para usar su event loop
    private ?Server $server = null;
    private array $transferMetadata = [];

    public function __construct(
        protected readonly ?LoggerInterface $logger = null
    )
    {
    }

    /**
     * Inicializa el cliente dentro del contexto de un Swoole\Server
     * Esto permite reutilizar el event loop existente
     */
    public function initializeWithServer(Server $server): void
    {
        $this->server = $server;
    }

    /**
     * Conecta a otro microservicio de forma asíncrona
     */
    public function connectToMicroservice(string $host, int $port, float $timeout = 0.5): void
    {
        $this->client = new Client(SWOOLE_SOCK_TCP | SWOOLE_SOCK_ASYNC);

        $this->client->on('connect', function (Client $client) use ($host, $port) {
            $this->logger?->info("Connected to microservice {$host}:{$port}");
            $this->processReconnectQueue();
        });

        $this->client->on('receive', $this->handleMicroserviceResponse(...));

        $this->client->on('error', function (Client $client) use ($host, $port) {
            $this->logger?->error("Connection error to microservice {$host}:{$port}");
            $this->handleConnectionFailure();
        });

        $this->client->on('close', function (Client $client) use ($host, $port) {
            $this->logger?->warning("Connection closed to {$host}:{$port}, attempting reconnect...");
            $this->scheduleReconnect($host, $port);
        });

        // Conectar sin bloquear
        $this->client->connect($host, $port, $timeout);
    }

    /**
     * Recupera los metadatos asociados a una transferencia
     */
    public function getTransferMetadata(string $transferId): ?FileTransferMetadata
    {
        return $this->transferMetadata[$transferId] ?? null;
    }

    /**
     * Envía un archivo a otro microservicio
     * Este método no bloquea y retorna inmediatamente
     */
    public function sendFileToMicroservice(
        string                $filePath,
        ?string               $fileName = null,
        ?CallbacksCollection  $onComplete = null,
        ?FileTransferMetadata $metadata = null
    ): ?string
    {
        if (!$this->client || !$this->client->isConnected()) {
            $this->logger?->error("Not connected to target microservice");
            return null;
        }

        if (!file_exists($filePath)) {
            $this->logger?->error("File not found: {$filePath}");
            return null;
        }

        $fileName = $fileName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $transferId = uniqid('m2m_transfer_', true);
        $totalChunks = (int)ceil($fileSize / self::CHUNK_SIZE);

        // Registrar transferencia
        $this->pendingTransfers[$transferId] = [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'chunks_sent' => 0,
            'total_chunks' => $totalChunks,
            'start_time' => microtime(true),
            'is_sender' => true,
            'status' => 'initializing',
        ];

        if ($onComplete !== null) {
            $this->sendCompleteCallbacks[$transferId] = $onComplete;
        }

        $this->logger?->info(
            "Sending file to microservice: {$fileName} ({$fileSize} bytes, {$totalChunks} chunks)"
        );

        // Enviar comando de inicio (asíncrono, no bloquea)
        $initPacket = json_encode([
            'action' => FileTransferActionsEnum::TransferInit->value,
            'transfer_id' => $transferId,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'chunk_size' => self::CHUNK_SIZE,
            'total_chunks' => $totalChunks,
            'metadata' => $metadata?->toArray() ?? []
        ]);

        $this->client->send($initPacket);

        // Iniciar envío de chunks usando el timer de Swoole (integrado al event loop)
        $this->scheduleChunkSending($transferId);

        return $transferId;
    }

    /**
     * Programa el envío de chunks usando el timer de Swoole
     * Esto se integra perfectamente con el event loop del servidor
     */
    private function scheduleChunkSending(string $transferId): void
    {
        if (!$this->server) {
            // Si no hay server, usar timer global de Swoole
            Timer::after(10, function () use ($transferId) {
                $this->sendNextChunks($transferId);
            });
            return;
        }

        // Usar el timer del server para mejor integración
        $this->server->after(10, function () use ($transferId) {
            $this->sendNextChunks($transferId);
        });
    }

    /**
     * Envía el siguiente lote de chunks
     */
    private function sendNextChunks(string $transferId): void
    {
        if (!isset($this->pendingTransfers[$transferId])) {
            return;
        }

        if (!$this->client || !$this->client->isConnected()) {
            $this->logger?->error("Connection lost during transfer {$transferId}");
            return;
        }

        $transfer = &$this->pendingTransfers[$transferId];

        if ($transfer['chunks_sent'] >= $transfer['total_chunks']) {
            return;
        }

        // Enviar hasta 5 chunks por ciclo para ser más conservador entre microservicios
        $chunksToSend = min(5, $transfer['total_chunks'] - $transfer['chunks_sent']);

        for ($i = 0; $i < $chunksToSend; $i++) {
            $chunkIndex = $transfer['chunks_sent'];

            if ($chunkIndex >= $transfer['total_chunks']) {
                break;
            }

            $offset = $chunkIndex * self::CHUNK_SIZE;
            $chunkData = @file_get_contents($transfer['file_path'], false, null, $offset, self::CHUNK_SIZE);

            if ($chunkData === false) {
                $this->handleTransferError($transferId, "Failed to read chunk {$chunkIndex}");
                return;
            }

            $chunkPacket = json_encode([
                'action' => FileTransferActionsEnum::TransferChunk->value,
                'transfer_id' => $transferId,
                'chunk_index' => $chunkIndex,
                'data' => base64_encode($chunkData)
            ]);

            $this->client->send($chunkPacket);
            $transfer['chunks_sent']++;
        }

        // Si quedan más chunks, programar siguiente ciclo
        if ($transfer['chunks_sent'] < $transfer['total_chunks']) {
            $this->scheduleChunkSending($transferId);
        } else {
            // Enviar notificación de completado
            $completePacket = json_encode([
                'action' => FileTransferActionsEnum::TransferComplete->value,
                'transfer_id' => $transferId,
                'total_chunks' => $transfer['total_chunks'],
                'transfer_time' => microtime(true) - $transfer['start_time']
            ]);

            $this->client->send($completePacket);
            $transfer['status'] = 'waiting_confirmation';

            $this->logger?->info("All chunks sent for transfer {$transferId}, awaiting confirmation");
        }
    }

    /**
     * Maneja respuestas del microservicio destino
     */
    private function handleMicroserviceResponse(Client $client, string $data): void
    {
        $packet = json_decode($data, true);

        if (!$packet || !isset($packet['transfer_id'])) {
            return;
        }

        $transferId = $packet['transfer_id'];

        // Verificar si es una respuesta a una transferencia que enviamos
        if (isset($this->pendingTransfers[$transferId]) && $this->pendingTransfers[$transferId]['is_sender']) {
            $this->handleSendResponse($transferId, $packet);
        }
        // También podría ser una solicitud de archivo entrante si implementamos esa dirección
    }

    /**
     * Procesa la respuesta del servidor a nuestro envío
     */
    private function handleSendResponse(string $transferId, array $packet): void
    {
        $transfer = $this->pendingTransfers[$transferId];
        $metadata = $this->transferMetadata[$transferId] ?? new FileTransferMetadata();

        if (isset($packet['error'])) {
            // El servidor reportó un error
            $this->handleTransferError($transferId, $packet['error']);
            return;
        }

        if ($packet['command'] ?? 0 === self::CMD_REQUEST_RESEND) {
            // El servidor solicita reenvío de chunks específicos
            $this->resendChunks($transferId, $packet['missing_chunks'] ?? []);
            return;
        }
        // El servidor puede devolver metadatos de respuesta
        $responseMetadata = isset($packet['response_metadata'])
            ? FileTransferMetadata::fromArray($packet['response_metadata'])
            : null;

        // Asumimos confirmación exitosa
        $elapsed = microtime(true) - $transfer['start_time'];
        $this->logger?->info(
            "Microservice confirmed transfer {$transferId} in {$elapsed}s"
        );

        $this->executeCallbacks($transferId, $transfer['file_path'], true, $metadata, $responseMetadata);
        $this->cleanupTransfer($transferId);
    }

    /**
     * Reenvía chunks específicos solicitados por el servidor
     */
    private function resendChunks(string $transferId, array $missingChunks): void
    {
        $transfer = $this->pendingTransfers[$transferId];

        $this->logger?->info("Resending " . count($missingChunks) . " chunks for {$transferId}");

        foreach ($missingChunks as $chunkIndex) {
            $offset = $chunkIndex * self::CHUNK_SIZE;
            $chunkData = @file_get_contents($transfer['file_path'], false, null, $offset, self::CHUNK_SIZE);

            if ($chunkData !== false) {
                $chunkPacket = json_encode([
                    'action' => FileTransferActionsEnum::TransferChunk->value,
                    'transfer_id' => $transferId,
                    'chunk_index' => $chunkIndex,
                    'data' => base64_encode($chunkData)
                ]);

                $this->client->send($chunkPacket);
            }
        }
    }

    private function handleTransferError(string $transferId, string $message): void
    {
        $this->logger?->error("Transfer {$transferId} error: {$message}");

        $transfer = $this->pendingTransfers[$transferId] ?? null;
        $filePath = $transfer['file_path'] ?? '';

        $this->executeCallbacks($transferId, $filePath, false);
        $this->cleanupTransfer($transferId);
    }

    private function executeCallbacks(
        string                $transferId,
        string                $finalPath, bool $success,
        ?FileTransferMetadata $requestMetadata = null,
        ?FileTransferMetadata $responseMetadata = null): void
    {
        if (!isset($this->sendCompleteCallbacks[$transferId])) {
            return;
        }

        $callbacks = $this->sendCompleteCallbacks[$transferId];
        unset($this->sendCompleteCallbacks[$transferId]);

        foreach ($callbacks as $callback) {
            try {
                $callback($transferId, $finalPath, $success, $requestMetadata, $responseMetadata);
            } catch (\Throwable $e) {
                $this->logger?->error(
                    "Error in transfer callback for {$transferId}: {$e->getMessage()}"
                );
            }
        }
    }

    private function cleanupTransfer(string $transferId): void
    {
        unset($this->pendingTransfers[$transferId],
            $this->transferMetadata[$transferId]);
    }

    private function scheduleReconnect(string $host, int $port): void
    {
        // Reintentar conexión después de 5 segundos
        if ($this->server) {
            $this->server->after(5000, function () use ($host, $port) {
                $this->connectToMicroservice($host, $port);
            });
        } else {
            Timer::after(5000, function () use ($host, $port) {
                $this->connectToMicroservice($host, $port);
            });
        }
    }

    private function processReconnectQueue(): void
    {
        // Procesar transferencias que estaban en cola durante la desconexión
        foreach ($this->reconnectQueue as $task) {
            $this->sendFileToMicroservice(
                $task['file_path'],
                $task['file_name'],
                $task['callbacks'] ?? null
            );
        }
        $this->reconnectQueue = [];
    }

    private function handleConnectionFailure(): void
    {
        // Marcar todas las transferencias pendientes como error
        foreach (array_keys($this->pendingTransfers) as $transferId) {
            $this->handleTransferError($transferId, "Connection lost to microservice");
        }
    }
}