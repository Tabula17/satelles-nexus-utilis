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
    protected string $origin = 'task:subscriber';

    public function __construct(HamumServerInterface $server, array $channels, ?LoggerInterface $logger = null)
    {
        $this->server = $server;
        $this->channels = $channels;
        $this->logger = $logger;
        $server->on('managerStart', fn() => $this->start());
        $server->on('managerStop', fn() => $this->stop());
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
        $this->logger->info("Subscriber process started for channels: " . implode(', ', $this->channels));
    }

    /**
     * Detiene el proceso suscriptor
     */
    public function stop(): void
    {
        if ($this->process && $this->running) {
            $this->running = false;
            Process::kill($this->process->pid, SIGTERM);
            $this->logger->info("Subscriber process stopped");
        }
    }

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