<?php

namespace Tabula17\Satelles\Utilis\Config;

use Swoole\Client;
use Tabula17\Satelles\Utilis\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Utilis\Exception\InvalidArgumentException;

class ConnectionConfig extends AbstractDescriptor
{

    /**
     * The name of the connection configuration.
     * This is used to uniquely identify the connection within the application.
     * @var string $name
     */
    protected(set) string $name {
        set(string $name) {
            $this->name = $name;
            $this->configId = uniqid($name . '::', false);
        }
    }
    protected(set) string $protocol = 'tcp';

    /**
     * The host address or IP of the database server.
     * @var string $host
     */
    protected(set) string $host = 'localhost' {
        /**
         * @throws InvalidArgumentException
         */
        set {
            $cleanedHost = trim($value);

            // 1. Validar que no esté vacío
            if ($cleanedHost === '') {
                throw new InvalidArgumentException('El host no puede estar vacío');
            }

            // 2. Validar si es un dominio/hostname válido OR una IP válida
            if (filter_var($cleanedHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ||
                filter_var($cleanedHost, FILTER_VALIDATE_IP)) {
                $this->host = $cleanedHost;
                return; // Salida temprana si es exitoso
            }

            // 3. Fallback de error
            throw new InvalidArgumentException(sprintf('El formato de host "%s" es inválido', $value));
        }
    }

    /**
     * The port number used to connect to the database server.
     * @var int|null $port
     */
    protected(set) ?int $port = null {
        /**
         * @throws InvalidArgumentException
         */
        set(?int $value) => ($value !== null && ($value < 1 || $value > 65535))
            ? throw new InvalidArgumentException('El puerto debe estar entre 1 y 65535')
            : $this->port = $value;
    }
    /**
     * The username used to authenticate with the database server.
     * @var string|null $username
     */
    protected(set) ?string $username;
    /**
     * The password associated with the provided username.
     * @var string|null $password
     */
    protected(set) ?string $password;
    /**
     * The name of the database to connect to.
     * @var string|null $dbname
     */
    protected(set) ?string $dbname;
    /**
     * The name of the collection to use for this connection.
     * @var string|null $collection
     */
    protected(set) ?string $collection;
    /**
     * Additional options to be passed to the PDO connection.
     * @var array $options
     */
    protected(set) array $options = [];
    /**
     * The path to the Unix socket file used for local connections.
     * @var string|null $unixSocket
     */
    protected(set) ?string $unixSocket;
    /**
     * The character set used for the connection.
     * @var string|null $charset
     */
    protected(set) ?string $charset;
    /**
     * The ID of the connection configuration.
     * This is automatically generated based on the configuration name.
     * @var string $configId
     */
    protected(set) string $configId;
    /**
     * A description of the connection configuration.
     * @var string $description
     */
    protected(set) string $description;
    /**
     * The maximum number of connections allowed for this configuration when used in a pool.
     * @var int $maxConnectons
     */
    protected(set) int $maxConnections;
    protected(set) float $dealy = 0;
    protected(set) string $lastConnectionError;

    private function tcpConnector(): ?Client
    {
        $client = new Client(SWOOLE_SOCK_TCP);
        if (!$client->connect($this->host, $this->port)) {
            return null;
        }
        return $client;

    }

    private function unixConnector(): ?Client
    {
        $client = new Client(SWOOLE_SOCK_UNIX_STREAM);
        if (!$client->connect($this->unixSocket)) {
            return null;
        }
        return $client;
    }

    public function getConnector(): mixed
    {
        if (isset($this->unixSocket)) {
            return $this->unixConnector();
        }
        return $this->tcpConnector();
    }

    public function getSafeData(): array
    {
        $data = $this->toArray();
        unset($data['password'], $data['name'], $data['id'], $data['options'], $data['maxConnections'], $data['delay'], $data['lastConnectionError']);
        return $data;
    }

    /**
     * Checks if a connection to the specified host and port can be established.
     *
     * @return bool Returns true if the connection is successful and the driver is available, otherwise false.
     */
    public function canConnect(): bool
    {
        // Para sockets Unix
        if (isset($this->unixSocket)) {
            return file_exists($this->unixSocket) && is_readable($this->unixSocket);
        }

        // Para conexión TCP
        try {
            $context = stream_context_create([
                'socket' => ['tcp_nodelay' => true],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);
            $host = $this->host;
            $port = $this->port;
            $scheme = $this->protocol;
            if (strpos($scheme, 'http') === 0) {
                // Determinar el puerto por defecto según el protocolo si no está explícito
                if ($scheme === 'https') {
                    $context = stream_context_create([
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        ]
                    ]);
                    if (!$port) {
                        $port = 443;
                    }
                }
                $scheme = $scheme === 'https' ? 'ssl' : 'tcp';
            } else {
                // Si es una conexión TCP tradicional por propiedades, ajustamos el esquema si es puerto 443
                $scheme = ($port === 443) ? 'ssl' : 'tcp';
            }


            $time = microtime(true);
            $socket = @stream_socket_client(
                "{$scheme}://{$host}:{$port}",
                $errno,
                $errstr,
                1, // timeout
                STREAM_CLIENT_CONNECT,
                $context
            );

            if ($socket) {
                $this->dealy = microtime(true) - $time;
                fclose($socket);
                return true;
            }
            $this->lastConnectionError = 'No se pudo acceder al host: ' . $errstr . ' (' . $errno . ')';

            return false;
        } catch (\Throwable $e) {
            $this->lastConnectionError = $e->getMessage();
            return false;
        }
    }

    public function getId(): string
    {
        return $this->configId;
    }

}