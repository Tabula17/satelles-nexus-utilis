<?php

namespace Tabula17\Satelles\Nexus\Utilis\Process;

use Psr\Log\LoggerInterface;
use Swoole\Server;
use Redis;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Utilis\Config\RedisConfig;
use Tabula17\Satelles\Utilis\Trait\CoroutineHelper;
use Throwable;

class RedisSubscriberProcess extends AbstractSubscriberProcess
{
    use CoroutineHelper;

    public function __construct(
        HamumServerInterface         $server,
        array                        $channels,
        private readonly RedisConfig $redisConfig,
        ?LoggerInterface             $logger = null
    )
    {
        parent::__construct($server, $channels, $logger = null);
    }

    protected function execute(): void
    {
        $interval = $this->redisConfig->retryInterval;
        if ($interval < 1) {
            $interval = 1; // Establecer un mínimo de 1 segundo para evitar bucles de reconexión demasiado rápidos
        }
        while ($this->running) {
            try {
                $redis = new Redis();
                if (!$this->connectWithRetry($redis)) {
                    $this->safeSleep($interval);
                    continue;
                }
                $this->logger?->debug("Conectado a Redis en {$this->redisConfig->host}:{$this->redisConfig->port}");
                // Suscribirse y procesar mensajes
                $redis->subscribe($this->channels, function ($instance, $channel, $message) {
                    $this->dispatchToTaskWorker($channel, $message);
                });

            } catch (Throwable $e) {
                $this->logger?->error("Error en suscriptor: " . $e->getMessage());
                $this->safeSleep($interval);
            }
        }
    }

    /**
     * Envía el mensaje a un Task Worker para procesar los callbacks
     */
    private function dispatchToTaskWorker(string $channel, string $message): void
    {
        $message = json_validate($message) ? json_decode($message, true) : $message;
        // Preparar la tarea
        $task = [
            'from' => $this->origin,
            'action' => $channel, // HamumTrait taskHandler usará el nombre del canal como acción para enrutar a los callbacks registrados
            'data' => $message,
            'timestamp' => microtime(true)
        ];
        $taskId = $this->server->task(json_encode($task), -1, fn($server, $taskId) => $this->logger?->debug("Task Worker ID: {$taskId} completado"));
        $this->logger?->debug("Mensaje en {$channel} enviado a Task Worker ID: {$taskId}");
    }

    private function connectWithRetry(Redis $redis): bool
    {
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts && $this->running) {
            try {
                if ($redis->connect($this->redisConfig->host, $this->redisConfig->port, 5)) {
                    if ($this->redisConfig->password && !$redis->auth($this->redisConfig->password)) {
                        throw new \RuntimeException("Autenticación fallida");
                    }
                    return true;
                }
            } catch (Throwable $e) {
                $this->logger?->warning("Intento " . ($attempt + 1) . " fallido: " . $e->getMessage());
            }

            $attempt++;
            if ($attempt < $maxAttempts) {
                sleep(2);
            }
        }

        return false;
    }

}