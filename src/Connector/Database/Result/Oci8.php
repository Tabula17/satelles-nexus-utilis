<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database\Result;

use Tabula17\Satelles\Nexus\Utilis\Connector\Database\ResultInterface;
use Traversable;

class Oci8 implements ResultInterface
{

    public function __construct(private mixed $statement)
    {
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Generator
    {
        oci_set_prefetch($this->statement, 500);
        while ($row = oci_fetch_array($this->statement, OCI_ASSOC + OCI_RETURN_NULLS)) {
            yield $row;
        }

        // 2. Crear las funciones de tipado una sola vez fuera del bucle
        $hydrator = $this->compileHydrator($typeMap);
        while ($row = oci_fetch_array($this->statement, OCI_ASSOC + OCI_RETURN_NULLS)) {
            // Normalizar a minúsculas (Oracle por defecto devuelve MAYÚSCULAS)
            $row = array_change_key_case($row, CASE_LOWER);

            // Aplicar tipado rápido
            yield $hydrator($row);
        }
        oci_free_statement($this->statement);
    }

    public function fetchAll(): array
    {
        $res = [];
        oci_fetch_all($this->statement, $res, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC);
        return $res;
    }

    private function compileHydrator(array $typeMap): callable
    {
        return static function (array $row) use ($typeMap) {
            foreach ($typeMap as $col => $type) {
                if (!isset($row[$col])) {
                    continue;
                }
                $row[$col] = match ($type) {
                    'int' => (int)$row[$col],
                    'float' => (float)$row[$col],
                    default => $row[$col]
                };
            }
            return $row;
        };
    }
}