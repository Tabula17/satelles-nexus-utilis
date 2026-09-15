<?php

namespace Tabula17\Satelles\Utilis\Config;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;
use Tabula17\Satelles\Utilis\Exception\InvalidArgumentException;

class TCPServerConfig extends AbstractDescriptor
{
//$host, $port = null, $mode = null, $sock_type = null

    /*
     * SWOOLE_SOCK_TCP tcp ipv4 socket
    SWOOLE_SOCK_TCP6 tcp ipv6 socket
    SWOOLE_SOCK_UDP udp ipv4 socket
    SWOOLE_SOCK_UDP6 udp ipv6 socket
    SWOOLE_SOCK_UNIX_DGRAM unix socket dgram
    SWOOLE_SOCK_UNIX_STREAM unix socket stream
     */

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
    protected(set) int $mode = SWOOLE_PROCESS {
        set => $this->type = match ($value) {
            SWOOLE_BASE,
            SWOOLE_PROCESS => $value,
            default => SWOOLE_PROCESS,
        };
    }
    protected(set) int $type = SWOOLE_SOCK_TCP {
        set => $this->type = match ($value) {
            SWOOLE_SOCK_TCP,
            SWOOLE_SOCK_TCP6,
            SWOOLE_SOCK_UDP,
            SWOOLE_SOCK_UDP6,
            SWOOLE_SOCK_UNIX_DGRAM,
            SWOOLE_SOCK_UNIX_STREAM => $value,
            default => SWOOLE_SOCK_TCP,
        };
    }
    /**
     * An associative array used to store configuration options -> https://wiki.swoole.com/en/#/server/setting
     * @var array $options
     */
    protected(set) array $options = [];
    protected(set) ?TCPSSLConfig $ssl {
        set(TCPSSLConfig|array|null $value) {
            if ($value instanceof TCPSSLConfig) {
                $this->ssl = $value;
            } else {
                $this->ssl = TCPSSLConfig::fromArray($value ?? []);
            }
        }
    }

    public function __construct(
        string        $host = 'localhost',
        ?int          $port = null,
        int           $mode = SWOOLE_PROCESS,
        int           $type = SWOOLE_SOCK_TCP,
        ?array        $options = null,
        ?TCPSSLConfig $ssl = null
    )
    {
        $this->host = $host;
        $this->port = $port;
        $this->mode = $mode;
        $this->type = $type;
        $this->options = $options ?? [];
        $this->ssl = $ssl;

        parent::__construct();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate(): void
    {
        if (empty($this->host)) {
            throw new InvalidArgumentException('El host no puede estar vacío');
        }
        if ($this->port !== null && ($this->port < 1 || $this->port > 65535)) {
            throw new InvalidArgumentException('El puerto debe estar entre 1 y 65535');
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function set(string $property, mixed $value): void
    {
        if ($property === 'ssl' && !($value instanceof TCPSSLConfig) && !is_array($value)) {
            throw new InvalidArgumentException('La configuración SSL debe ser una instancia de TCPSSLConfig o un array');
        }
        parent::set($property, $value);
    }

}