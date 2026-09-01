<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

use Tabula17\Satelles\Nexus\Utilis\Exception\ExceptionDefinitions;
use Tabula17\Satelles\Nexus\Utilis\Exception\InvalidArgumentException;

enum DriversEnum: string
{
    case MYSQL = 'mysql';
    case SQLSRV = 'sqlsrv';
    case PGSQL = 'pgsql';
    case SQLITE = 'sqlite';
    case ORACLE = 'oci';

    /**
     * @throws InvalidArgumentException
     */
    public static function fromName(string $value): DriversEnum
    {
        return match ($value) {
            'mysql', 'mysqli', 'mariadb', 'maria' => self::MYSQL,
            'mssql', 'sqlsrv' => self::SQLSRV,
            'pgsql', 'postgres', 'postgresql' => self::PGSQL,
            'sqlite' => self::SQLITE,
            'oracle', 'oci', 'oci8' => self::ORACLE,
            default => throw new InvalidArgumentException(sprintf(ExceptionDefinitions::DATABASE_DRIVER_NOT_SUPPORTED->value, $value))
        };
    }

    public static function getAvailableDrivers(): array
    {
        return array_map(static fn($enum) => $enum->value, self::cases());
    }

    public static function isSupported(string $driver): bool
    {
        return in_array($driver, self::getAvailableDrivers(), true);
    }

    public function native(): string
    {
        return match ($this) {
            self::MYSQL => 'mysqli',
            self::SQLSRV => 'sqlsrv',
            self::PGSQL => 'pgsql',
            self::SQLITE => 'sqlite',
            self::ORACLE => 'oci8',
        };
    }

    public function supportNative(): bool
    {
        return extension_loaded($this->native());
    }

    public function pdo(): string
    {
        return 'pdo_' . $this->value;
    }

    public function supportPdo(): bool
    {
        return extension_loaded($this->pdo());
    }

    public static function isAvailable(string|self $driver): bool
    {
        if (is_string($driver) && !self::isSupported($driver)) {
            return false;
        }
        $driver = $driver instanceof self ? $driver : self::fromName($driver);
        return $driver->supportPdo() || $driver->supportNative();
    }
}