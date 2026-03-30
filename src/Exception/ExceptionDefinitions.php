<?php

namespace Tabula17\Satelles\Nexus\Utilis\Exception;

enum ExceptionDefinitions: string
{
    case POOL_SIZE_GREATER_THAN_ZERO = 'El tamaño del pool debe ser mayor que cero ';
    case POOL_NOT_FOUND = 'No se encontró el pool de conexiones "%s"';
    case POOL_CONNECTION_TIMEOUT = 'Tiempo de espera agotado para la conexión al pool de conexiones "%s" (%s segundos)';
    case POOL_CONNECTION_ERROR = 'Error al conectarse al pool de conexiones "%s": %s';
    case HOST_CANNOT_BE_EMPTY = 'El host no puede estar vacío';
    case HOST_INVALID = 'El formato de host "%s" es inválido';
    case PORT_INVALID = 'El puerto debe estar entre 1 y 65535';

}
