<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database\Result;

use Tabula17\Satelles\Nexus\Utilis\Connector\Database\ResultInterface;
use Tabula17\Satelles\Utilis\Collection\DataModelCollection;

readonly class OciResult implements ResultInterface
{

    public function __construct(
        private mixed               $statement,
        private DataModelCollection $model
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Generator
    {
        oci_set_prefetch($this->statement, 500);
        // 2. Crear las funciones de tipado una sola vez fuera del bucle
        $hydrator = $this->compileHydrator();
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
        oci_free_statement($this->statement);
        $hydrator = $this->compileHydrator();
        foreach ($res as &$row) {
            $row = array_change_key_case($row, CASE_LOWER);
            $row = $hydrator($row);
        }
        return $res;
    }
    public function fetchOne(): ?array
    {
        $row = oci_fetch_array($this->statement, OCI_ASSOC + OCI_RETURN_NULLS);
        if ($row === false) {
            return null;
        }
        $row = array_change_key_case($row, CASE_LOWER);
        $hydrator = $this->compileHydrator();
        return $hydrator($row);
    }
    public function fetchColumn(int $columnIndex = 0): array
    {
        $res = [];
        while ($row = oci_fetch_array($this->statement, OCI_NUM + OCI_RETURN_NULLS)) {
            $res[] = $row[$columnIndex] ?? null;
        }
        oci_free_statement($this->statement);
        return $res;
    }
    public function rowCount(): int
    {
        return oci_num_rows($this->statement);
    }

    private function compileHydrator(): callable
    {
        return function (array $row) {
            foreach ($row as $key => $value) {
                $row[$key] = $this->model->get($key)?->hydrate($value) ?? $value;
            }
            return $row;
        };
    }

    public static function fromStatement(mixed $statement, DataModelCollection $model): static
    {
        return new static($statement, $model);
    }

    public function __destruct()
    {
        oci_free_statement($this->statement);
    }

}