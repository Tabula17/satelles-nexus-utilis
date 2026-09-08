<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

enum PoolStatusEnum
{
    case OK;
    case ERROR;
    case BUSY;
    case EMPTY;
    case UNREACHABLE;

    public function describe(): string
    {
        return match ($this) {
            self::OK => 'OK -> El funcionamiento del pool de conexiones es correcto.',
            self::ERROR => 'ERROR -> Se produjeron errores al intentar conectar con la base de datos.',
            self::BUSY => 'BUSY -> Todas las conexiones están ocupadas.',
            self::EMPTY => 'EMPTY -> No hay conexiones disponibles.',
            self::UNREACHABLE => 'UNREACHABLE -> No se pudo conectar con la base de datos.',
        };
    }
}
