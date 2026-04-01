<?php

namespace Tabula17\Satelles\Nexus\Utilis\Process;

use Psr\Log\LoggerInterface;
use Swoole\Process;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;

abstract class AbstractSubscriberProcess
{
    protected HamumServerInterface $server;
    protected Process $process;
    protected array $channels;
    protected bool $running = false;
    protected ?LoggerInterface $logger = null;
    public string $origin = 'task:subscriber';

    public function __construct(HamumServerInterface $server, array $channels, ?LoggerInterface $logger = null)
    {
        $this->server = $server;
        $this->channels = $channels;
        $this->logger = $logger;
    }

    public function addChannel(string $channel, ?callable $handler, bool $own = true): void
    {
        $this->channels[] = $channel;
        if ($handler) {
            $this->server->registerTaskHandlers($channel, $handler, $own ? $this->origin : 'task:subscriber');
        }
        if ($this->running) {
            $this->logger?->info("📰 Channel added: $channel. Restarting subscriber process to apply changes.");
            $this->stop();
            $this->start();
        }
    }

    public function removeChannel(string $channel): void
    {
        unset($this->channels[array_search($channel, $this->channels, true)]);
    }

    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Inicia el proceso suscriptor
     */
    public function start(): void
    {
        $this->process = new Process(function () {
            $this->running = true;
            $this->execute();
        }, false, 2, true); // redirect stdin/stdout/stderr = true
        $this->process->start();
        // Registrar el PID para limpieza posterior
        $this->logger?->info("📰 Subscriber process started for channels: " . implode(', ', $this->channels));
    }

    /**
     * Detiene el proceso suscriptor
     */
    public function stop(): void
    {
        if ($this->process && $this->running) {
            $this->running = false;
            Process::kill($this->process->pid, SIGTERM);
            $this->logger?->info("📰 Subscriber process stopped");
        }
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Inicializa si es necesario el suscriptor al agregarse a la instancia de Server si se usa el Trait ProcessSubscriberTrait. (debe implementarse)
     *
     * @return void
     */
    abstract public function init(): void;

    /**
     * Lógica principal del suscriptor (debe implementarse)
     */
    abstract protected function execute(): void;

    /**
     * Maneja un mensaje recibido
     *
     * abstract protected function handleMessage(string $channel, string $message): void;
     */
    /**
     * Envía mensaje a todos los workers del servidor
     *
     * protected function sendToWorkers(array $data): void
     * {
     * $this->server->notifyToWorkers($data);
     * }
     */
}