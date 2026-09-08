<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

enum OperationsEnum
{
    case SELECT;
    case INSERT;
    case UPDATE;
    case DELETE;
    case EXECUTE;


    public function canHaveResultset(): bool
    {
        return match ($this) {
            self::SELECT => true,
            default => false,
        };
    }

    public function canModifyData(): bool
    {
        return match ($this) {
            self::INSERT, self::UPDATE, self::DELETE => true,
            default => false,
        };
    }

    public function canExecute(): bool
    {
        return match ($this) {
            self::EXECUTE => true,
            default => false,
        };
    }
}
