<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Hamum;

use Psr\Log\LoggerInterface;
use Swoole\Coroutine\System;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Tabula17\Satelles\Nexus\Utilis\Server\MimeTypes;
use Tabula17\Satelles\Nexus\Utilis\Server\Trait\HamumTrait;
use Tabula17\Satelles\Utilis\Collection\CallableCollection;
use Tabula17\Satelles\Utilis\Config\TCPServerConfig;

abstract class Graphema extends Server implements HamumServerInterface
{
    use HamumTrait;

    final const HamumTypes TYPE = HamumTypes::HTTP;

    protected array $requestHandlers = [];

    public function __construct(TCPServerConfig $config, public ?LoggerInterface $logger = null, protected readonly string $htmlFilesPath = './public/')
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

    private function searchFileFromRequestUri(string $requestUri): string|false
    {
        $requestUri = ltrim($requestUri, '/');
        $requestPath = explode('/', explode('?', $requestUri)[0]);
        $file = '';
        while (count($requestPath) > 0) {
            $file = rtrim('/' . array_pop($requestPath) . '/' . ltrim($file, '/'), '/');
            if (file_exists($this->makePathFile($file))) {
                return $file;
            }
        }
        return false;
    }

    protected function handleHtmlContent(string $filePath, Response $response): void
    {
        $filePath = $this->makePathFile($filePath);
        $this->logger?->debug("🔖 Getting file: {$filePath}");

        if (is_dir($filePath)) {
            $indexes = ['index.html', 'index.htm', 'index.php'];
            while (count($indexes) > 0 && !file_exists($filePath .= '/' . $indexes[0])) {
                array_shift($indexes);
            }
        }
        if (file_exists($filePath)) {
            $this->logger?->debug("🎯 Encontrada! Sending file: {$filePath}");
            $response->header('Content-Type', MimeTypes::fromFile($filePath)->contentType('UTF-8'));
            $response->header('Content-Length', filesize($filePath));
            $response->end(file_get_contents($filePath));
        } else {
            $this->logger?->debug("🙅🏼‍♂️ 404 Not Found: {$filePath}");
            $this->sendHttpError($response, GraphemaHttpCodes::NOT_FOUND);
        }
    }

    private function makePathFile(string $file): string
    {
        $file = $this->htmlFilesPath . '/' . ltrim(str_replace(array($this->htmlFilesPath, '\\'), array('', '/'), $file), '/');
        $this->logger?->debug("looking for file: {$file}");
        return $file;
    }

    protected function sendHttpError(Response $response, GraphemaHttpCodes $err = GraphemaHttpCodes::NOT_FOUND): void
    {
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $content = $err->fromPath($this->htmlFilesPath);
        $response->header('Content-Length', strlen($content));
        $response->status($err->httpCode());
        $response->end($content);
    }

    public function staticRequest(string $route, Request $request, Response $response): bool
    {
        $path = $request->server['request_uri'] ?? '/';
        $path = trim($path, '/');
        $route = trim($route, '/');
        $routeMatches = $path === $route;
        if (!$routeMatches) {
            $this->logger?->debug("Static route fails: $path !== $route");
            $this->sendHttpError($response);
        }
        return $routeMatches;
    }

    public function handleRequestEvent(Request $request, Response $response): void
    {
        $this->logger?->debug("🧩 Definition received from {$request->fd}:  " . $request->server['request_uri']);
        $this->logger?->debug("🧩 Definition handlers: " . json_encode(array_keys($this->requestHandlers)));

        $request->server['server_id'] = $this->getServerId();
        $request->server['html_files_path'] = $this->htmlFilesPath;

        if (strlen($request->server['request_uri']) === 1) {
            $cleanRequestUri = $request->server['request_uri'];
            $requestPath = [$cleanRequestUri];
        } else {
            $cleanRequestUri = rtrim($request->server['request_uri'], '/');
            $requestPath = explode('/', explode('?', trim($request->server['request_uri'], '/'))[0]);
        }

        $protocols = [];
        $file = '';
        while (count($requestPath) > 0) {
            $action = '/' . trim(implode('/', $requestPath), '/');
            $protocols[$action] = $file;
            $file = rtrim('/' . array_pop($requestPath) . '/' . ltrim($file, '/'), '/');
        }
        $eventHandlers = [];

        foreach ($protocols as $protocolAction => $file) {
            $this?->logger?->debug("🧩 Handling request event with protocolAction: {$protocolAction}");
            $eventHandlers = array_merge($this->getRequestHandlers($protocolAction), $this->getRequestHandlers('*'));
            if (!empty($eventHandlers)) {
                if ($protocolAction === $cleanRequestUri) {
                    $this->logger?->debug("🧩 Request {$cleanRequestUri} is same as {$protocolAction}. Ending request handling and sending to handler.");
                    break;
                }
                $path = $this->makePathFile($file);
                $this->logger?->debug("🧩 Checking {$protocolAction} => {$cleanRequestUri}. Checking if file exists: {$path}");

                if (file_exists($path)) {
                    $this->handleHtmlContent($file, $response);
                    return;
                }
                $this->logger?->debug("🙅🏼‍♂️ Not luck, file not found: {$file}");
            }
        }
        if (empty($eventHandlers)) {
            $ifFile = $this->searchFileFromRequestUri($cleanRequestUri);
            $this->logger?->debug("🗃️ Checking if file exists: {$this->makePathFile($ifFile)}");
            if ($ifFile) {
                $this->handleHtmlContent($ifFile, $response);
                return;
            }
            $this->sendHttpError($response, GraphemaHttpCodes::NOT_FOUND);
        } else {
            $this->logger?->debug("🧩 Executing request handlers for {$cleanRequestUri}");
            foreach ($eventHandlers as $callback) {
                $callback($request, $response);
            }
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
        $protocolAction = rtrim($protocolAction, '/');
        if (!str_starts_with($protocolAction, '/')) {
            $protocolAction = '/' . $protocolAction;
        }
        $this->logger?->debug("📝 Registering request handler for protocolAction: {$protocolAction} and protocol: {$protocol}");
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