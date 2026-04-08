<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Hamum;

use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Tabula17\Satelles\Nexus\Utilis\Server\Trait\HamumTrait;
use Tabula17\Satelles\Utilis\Collection\CallableCollection;
use Tabula17\Satelles\Utilis\Config\TCPServerConfig;

abstract class Graphema extends Server implements HamumServerInterface
{
    use HamumTrait;

    final const HamumTypes TYPE = HamumTypes::HTTP;

    protected array $requestHandlers = [];

    public function __construct(TCPServerConfig $config, public ?LoggerInterface $logger = null)
    {
        parent::__construct($config->host, $config->port, $config->mode ?? SWOOLE_BASE, $config->type ?? SWOOLE_SOCK_TCP);
        $sslEnabled = isset($config->ssl) && $config->ssl->enabled;
        $options = $sslEnabled ? array_merge($config->options, $config->ssl->toArray()) : $config->options;
        if ($sslEnabled) {
            unset($options['enabled']);
        }
        $this->set($options);
        $this->init();
        if (empty($this->requestHandlers)) {
            if (!$this->logger) {
                trigger_error("⚠️ No request handlers registered. Please register request handlers using registerRequestHandlers() method before starting the server.", E_USER_WARNING);
            } else {
                $this->logger->warning("⚠️ No request handlers registered. Please register request handlers using registerRequestHandlers() method before starting the server.");
            }
        }
    }

    abstract protected function init(): void;

    public function isHamumEnabled(): bool
    {
        return defined(static::class . '::HAMUM_ENABLED') && static::HAMUM_ENABLED;
    }

    public function isCronosEnabled(): bool
    {
        return defined(static::class . '::CRONOS_ENABLED') && static::CRONOS_ENABLED;
    }

    public function isProcessSubscriberEnabled(): bool
    {
        return defined(static::class . '::PROCESS_SUBSCRIBER_ENABLED') && static::PROCESS_SUBSCRIBER_ENABLED;
    }

    public function isClientInfoEnabled(): bool
    {
        return defined(static::class . '::CLIENT_INFO_ENABLED') && static::CLIENT_INFO_ENABLED;
    }

    public function handleRequestEvent(Request $request, Response $response): void
    {
        $this->logger?->debug("Definition received from {$request->fd}:  " . $request->server['request_uri']);
        $this->logger?->debug("Definition handlers: " . json_encode(array_keys($this->requestHandlers)));
        //$request->rawContent();
        //$data = var_export($request->get, true);
        $protocolAction = $request->server['request_uri'];
        //string $protocolAction,
        $this?->logger?->debug("Handling request event with protocolAction: {$protocolAction}");
        $eventHandlers = array_merge($this->getEventActionHandlers('request', $protocolAction), $this->getEventActionHandlers('request', '*'));
        foreach ($eventHandlers as $callback) {
            $callback($request, $response);
        }
        /**
         *  //todo: evaluar posibilidades de generar una respuesta final.
         * Posibilidades: Almacenar en una matriz el resultado de cada callback.
         * Si la matriz tiene mas de un resultado devolver mediante $response->write() cada resultado, y al finalizar el foreach hacer un $response->end().
         * Si tiene un solo resultado, no es necesario hacer nada, solo devolver el resultado en el response->end();
         * Otra posibilidad es que esa matriz de resultados sea evaluada por el protocolo que genera ek response final.
         * Aunque es dificil de implementar, se podría generar un protocolo de respuesta que se encargue de evaluar cada resultado y generar una respuesta final.
         * Esto permitiría una mayor flexibilidad en la generación de respuestas,
         * ya que cada callback podría devolver un resultado diferente y el protocolo de respuesta se encargaría de evaluarlos y generar una respuesta final coherente.
         * Lo único que habría que tener en cuenta es que solo se pueda registrar un protocolo de respuesta único por cada 'action' de request,
         * para evitar conflictos en la generación de respuestas.
         * aunque tal vez no está mal que todos puedan enviar la respuesta final.
         * Si un protocolo se encarga de evaluar la autenticación o autorización en cada request y esta es inválida
         * es lógico que envíe una respuesta final y no se ejecute la siguiente callback.
         * Entonces en este escenario lo que hay que tener en cuenta es el orden de ejecución de las callbacks.
         */
    }

    public function registerRequestHandlers(string $protocolAction, callable $callback, $protocol = 'generic'): void
    {
        //todo: necesitamos agregar los protocolos de manera que sea una cascada de ejecuciones y solo el último envíe el response o el end.
        if (!isset($this->requestHandlers[$protocolAction]) || !($this->requestHandlers[$protocolAction] instanceof CallableCollection)) {
            $this->requestHandlers[$protocolAction] = new CallableCollection();
        }
        $this->requestHandlers[$protocolAction]->offsetSet($protocol, $callback);
        $this->registerEventHandlers('request');
    }

    public function getRequestHandlers(string $protocolAction): ?array
    {
        return $this->getEventActionHandlers('request', $protocolAction);
    }

    public function hasRequestHandlers(string $protocolAction): bool
    {
        return $this->requestHandlers[$protocolAction]->offsetExists($protocolAction) && $this->requestHandlers[$protocolAction]->count() > 0;
    }

    public function removeRequestHandlers(string $protocolAction): void
    {
        $this->requestHandlers[$protocolAction]?->clear();
        unset($this->requestHandlers[$protocolAction]);
        $this->registerEventHandlers('request');
    }

    /**
     * Get the registered routes.
     * @return array<string>
     */
    public function getRegisteredRoutes(): array
    {
        return array_keys($this->requestHandlers);
    }
}