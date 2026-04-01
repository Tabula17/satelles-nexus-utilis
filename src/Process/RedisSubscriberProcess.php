<?php

namespace Tabula17\Satelles\Nexus\Utilis\Process;

use Psr\Log\LoggerInterface;
use Redis;
use Swoole\Coroutine;
use Swoole\Timer;
use Tabula17\Satelles\Nexus\Utilis\Exception\RuntimeException;
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
        $this->origin = 'task:subscriber:redis';
        parent::__construct($server, $channels, $logger);
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
                $redis->setOption(Redis::OPT_READ_TIMEOUT, -1);
                if (!$this->connectWithRetry($redis)) {
                    $this->safeSleep($interval);
                    continue;
                }
                $this->logger?->debug("🍭 Conectado a Redis en {$this->redisConfig->host}:{$this->redisConfig->port}");

                try {
                    $heartbeatTimer = Timer::tick(60000, function () {
                        try {
                            $pingRedis = new Redis();
                            $pingRedis->connect($this->redisConfig->host, $this->redisConfig->port, 1);
                            if (isset($this->redisConfig->password)) {
                                $pingRedis->auth($this->redisConfig->password);
                            }
                            $pingRedis->ping();
                            $pingRedis->close();
                            $this->logger?->debug("❤️ RedisSubscriber Heartbeat ejecutado. PID: " . getmypid() . " CID: " . Coroutine::getCid());
                        } catch (Throwable $e) {
                            $this->logger?->warning("💔 RedisSubscriber Heartbeat falló, forzando reconexión. PID: " . getmypid() . " CID: " . Coroutine::getCid());
                            throw new RuntimeException("RedisSubscriber Heartbeat failed: " . $e->getMessage());
                        }
                    });
                } catch (Throwable $ignored) {
                    $this->logger?->warning("❤️‍🩹 Error al iniciar RedisSubscriber heartbeat: " . $ignored->getMessage() . ". PID: " . getmypid() . " CID: " . Coroutine::getCid());

                }

                // Suscribirse y procesar mensajes
                $redis->subscribe($this->channels, function ($instance, $channel, $message) {
                    $this->dispatchToTaskWorker($channel, $message);
                });

            } catch (Throwable $e) {
                $this->logger?->error("🍭 Error en suscriptor: " . $e->getMessage() . " CID " . Coroutine::getCid());// Limpiar heartbeat
                if (isset($heartbeatTimer)) {
                    Timer::clear($heartbeatTimer);
                }
                if (isset($redis)) {
                    try {
                        $redis->close();
                    } catch (Throwable $ignored) {
                    }
                }
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
        $task = [];
        if (!isset($message['payload'])) {
            $task['payload'] = $message;
        } else {
            $task = $message;
        }
        if (!isset($task['action'])) {
            $task['action'] = $message['action'] ?? $channel;
        }
        if (!str_starts_with($task['action'], 'task:')) {
            $task['action'] = 'task:' . $task['action'];
        }
        $task['channel'] = $message['channel'] ?? $channel;
        $task['origin'] = $this->origin . ':' . $channel;
        $task['timestamp'] = microtime(true);

        // Preparar la tare\a
        $task = new TaskSubscriberDescriptor($task);
        if (!$task->isValid()) {
            $this->logger?->notice("🍭 Mensaje recibido en {$channel} no es un TaskSubscriberDescriptor válido, ignorando. Payload: " . json_encode($message));
            $this->logger?->debug("🍭 Task: " . $task->getJSON());
            return;
        }
        //$taskId = $this->server->task(json_encode($task), -1, fn($server, $taskId) => $this->logger?->debug("Task Worker ID: {$taskId} completado"));
        $taskId = $this->server->task($task->getJSON());
        $this->logger?->debug("🍭 Mensaje en {$channel} enviado a Task Worker ID: {$taskId}");
    }

    private function connectWithRetry(Redis $redis): bool
    {
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts && $this->running) {
            try {
                if (
                    $redis->connect(
                        host: $this->redisConfig->host,
                        port: $this->redisConfig->port,
                        timeout: $this->redisConfig->connectTimeout,
                        retry_interval: $this->redisConfig->retryInterval,
                        read_timeout: $this->redisConfig->readTimeout
                    )
                ) {
                    if (isset($this->redisConfig->password) && !$redis->auth($this->redisConfig->password)) {
                        throw new RuntimeException("🍭 Autenticación fallida");
                    }
                    return true;
                }
            } catch (Throwable $e) {
                $this->logger?->warning("🍭 Intento " . ($attempt + 1) . " fallido: " . $e->getMessage());
            }

            $attempt++;
            if ($attempt < $maxAttempts) {
                $this->safeSleep(1);
            }
        }

        return false;
    }

    public function init(): void
    {
        $this->logger?->info("🍭 Configurando eventos de suscriptor de Redis en {$this->redisConfig->host}:{$this->redisConfig->port}");
        $this->server->on('managerStart', fn() => $this->start());
        $this->server->on('managerStop', fn() => $this->stop());
    }
}