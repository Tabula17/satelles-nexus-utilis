<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server;

use Swoole\Server;
use Psr\Log\LoggerInterface;

/**
 * Clase abstracta que extiende Swoole\Server añadiendo funcionalidad de hooks
 *
 * Permite registrar callbacks que se ejecutan antes (before) y después (after)
 * de eventos específicos del servidor.
 */
abstract class HookableServer extends Server
{
    /**
     * Eventos que son privados y no deben ser sobrescritos directamente
     */
    private array $privateEvents = [
        'start',
        'onBeforeShutdown',
        'open',
        'message',
        'close',
        'request',
        'pipeMessage',
        'task',
        'workerStart',
        'workerStop',
    ];

    /**
     * Eventos que soportan hooks 'before' y/o 'after'
     */
    private array $hookableEvents = [
        'start' => ['before', 'after'],
        'reload' => ['before', 'after'],
        'shutdown' => ['before', 'after'],
        'stop' => ['before', 'after'],
        'close' => ['before', 'after'],
        'pause' => ['before', 'after'],
        'resume' => ['before', 'after']
    ];

    /**
     * Almacena los callbacks para los hooks
     */
    private array $eventHooks = [];

    /**
     * Logger opcional
     */
    public ?LoggerInterface $logger = null {
        set {
            $this->logger = $value;
        }
    }

    /**
     * Verifica si un evento es privado
     */
    private function eventIsPrivate(string $event_name): bool
    {
        return in_array($event_name, $this->privateEvents, true);
    }

    /**
     * Verifica si un evento soporta hooks
     */
    private function eventIsHookable(string $event_name, string $when): bool
    {
        return isset($this->hookableEvents[$event_name])
            && in_array($when, $this->hookableEvents[$event_name], true);
    }

    /**
     * Registra un hook para un evento
     */
    private function onEventHook(string $event_name, callable $callback, string $when = 'after'): bool
    {
        if (!$this->eventIsHookable($event_name, $when)) {
            $this->logger?->warning("Evento $event_name no soporta hooks en $when");
            return false;
        }

        $prop = $when . ucfirst($event_name);
        if (!isset($this->eventHooks[$prop])) {
            $this->eventHooks[$prop] = [];
        }

        if (!in_array($callback, $this->eventHooks[$prop], true)) {
            $this->eventHooks[$prop][] = $callback;
            return true;
        }

        return false;
    }

    /**
     * Elimina un hook
     */
    private function offEventHook(string $event_name, callable $callback, string $when = 'after'): bool
    {
        $prop = $when . ucfirst($event_name);
        if (isset($this->eventHooks[$prop])) {
            $this->eventHooks[$prop] = array_diff($this->eventHooks[$prop], [$callback]);
            return true;
        }
        return false;
    }

    /**
     * Registra un callback para ejecutar DESPUÉS de un evento
     */
    public function onAfter(string $event_name, callable $callback): bool
    {
        return $this->onEventHook($event_name, $callback, 'after');
    }

    /**
     * Elimina un callback de after
     */
    public function offAfter(string $event_name, callable $callback): bool
    {
        return $this->offEventHook($event_name, $callback, 'after');
    }

    /**
     * Registra un callback para ejecutar ANTES de un evento
     */
    public function onBefore(string $event_name, callable $callback): bool
    {
        return $this->onEventHook($event_name, $callback, 'before');
    }

    /**
     * Elimina un callback de before
     */
    public function offBefore(string $event_name, callable $callback): bool
    {
        return $this->offEventHook($event_name, $callback, 'before');
    }

    /**
     * Sobrescribe el método on de Swoole para manejar eventos privados
     */
    public function on(string $event_name, callable $callback): bool
    {
        if ($this->eventIsPrivate($event_name)) {
            if ($this->eventIsHookable($event_name, 'after') && $this->onAfter($event_name, $callback)) {
                $this->logger?->warning(
                    "Evento privado $event_name registrado como after::$event_name"
                );
            } else {
                $this->logger?->warning("Evento privado $event_name no permitido");
            }
            return false;
        }

        return parent::on($event_name, $callback);
    }

    /**
     * Sobrescribe off para manejar hooks
     */
    public function off(string $event_name, callable $callback): bool
    {
        if ($this->eventIsPrivate($event_name)) {
            $this->offAfter($event_name, $callback);
            return false;
        }

        $this->offEventHook($event_name, $callback, 'before');
        $this->offEventHook($event_name, $callback, 'after');

        return true;//parent::off($event_name, $callback);
    }

    /**
     * Registra un evento privado (para uso interno)
     */
    protected function onPrivateEvent(string $event_name, callable $callback): bool
    {
        return parent::on($event_name, $callback);
    }

    /**
     * Ejecuta los hooks registrados para un evento
     */
    protected function runEventActions(string $event_name, array $args, string $when = 'after'): void
    {
        $this->logger?->debug("Ejecutando hooks $when para $event_name");

        $prop = $when . ucfirst($event_name);
        if (isset($this->eventHooks[$prop])) {
            foreach ($this->eventHooks[$prop] as $callback) {
                $callback(...$args);
            }
        }
    }

    /**
     * Métodos hookeados que pueden ser sobrescritos por clases hijas
     */

    public function start(): bool
    {
        $this->logger?->info("Iniciando servidor...");
        $this->runEventActions('start', [], 'before');
        // El after se ejecuta vía evento privado 'start'
        return parent::start();
    }

    public function stop(int $workerId = -1, bool $waitEvent = false): bool
    {
        $this->logger?->info("Deteniendo servidor...");
        $args = func_get_args();
        $this->runEventActions('stop', $args, 'before');
        $stopped = parent::stop($workerId, $waitEvent);
        $this->runEventActions('stop', $args, 'after');
        return $stopped;
    }

    public function shutdown(): bool
    {
        $this->logger?->info("Apagando servidor...");
        $this->cleanUpServerResources();
        $this->runEventActions('shutdown', [], 'before');
        $shutdown = parent::shutdown();
        $this->runEventActions('shutdown', [], 'after');
        return $shutdown;
    }

    public function reload(bool $only_reload_taskworker = false): bool
    {
        $this->logger?->info("Recargando servidor...");
        $this->runEventActions('reload', [], 'before');
        $reloaded = parent::reload($only_reload_taskworker);
        $this->runEventActions('reload', [], 'after');
        return $reloaded;
    }

    public function close(int $fd, bool $reset = false): bool
    {
        $this->runEventActions('close', [$fd, $reset], 'before');
        $closed = parent::close($fd, $reset);
        $this->runEventActions('close', [$fd, $reset], 'after');
        return $closed;
    }

    public function pause(int $fd): bool
    {
        $this->runEventActions('pause', [$fd], 'before');
        $paused = parent::pause($fd);
        $this->runEventActions('pause', [$fd], 'after');
        return $paused;
    }

    public function resume(int $fd): bool
    {
        $this->runEventActions('resume', [$fd], 'before');
        $resumed = parent::resume($fd);
        $this->runEventActions('resume', [$fd], 'after');
        return $resumed;
    }

    abstract protected function cleanUpServerResources(): void;

}