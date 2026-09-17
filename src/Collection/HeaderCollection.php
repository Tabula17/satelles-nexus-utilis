<?php

namespace Tabula17\Satelles\Utilis\Collection;

use Tabula17\Satelles\Utilis\Config\HeaderDescriptor;
use Tabula17\Satelles\Utilis\Exception\InvalidArgumentException;
use Tabula17\Satelles\Utilis\Exception\UnexpectedValueException;

class HeaderCollection extends TypedCollection
{
    /**
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function __construct(iterable $headers = [])
    {
        /*
         * posibilidades:
         * - array de HeaderDescriptor
         * - array de string con el formato "nombre: valor"
         * - array de array con el formato ["nombre" => "valor"]
         * - string con el formato "nombre: valor"
         * - string con el formato "nombre: valor\r\n otro: valor"
         *
         * 1. Si es un array de HeaderDescriptor, se crea una instancia de HeaderCollection con esos headers.
         * 2. Si es un array de string con el formato "nombre: valor", se crea una instancia de HeaderDescriptor con esos headers.
         * 3. Si es un array de array con el formato ["nombre" => "valor"], se crea una instancia de HeaderDescriptor con esos headers.
         * 4. Si es una cadena con el formato "nombre: valor", se crea una instancia de HeaderDescriptor con esos headers.
         * 5. Si es una cadena con el formato "nombre: valor\r\n otro: valor", se crea una instancia de HeaderDescriptor con esos headers.
         */

        foreach ($headers as $key => $header) {
            if (is_string($key)) {
                if (is_array($header)) {
                    $header = new HeaderDescriptor($key, ...$header);
                } else {
                    $header = new HeaderDescriptor($key, $header);
                }
            }
            if (is_int($key) && is_array($header) && count($header) === 1) {
                $header = new HeaderDescriptor(key($header), current($header));
            }
            $this->add($header);
        }
        parent::__construct();
    }

    protected static function getType(): string
    {
        return HeaderDescriptor::class;
    }

    /**
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function add(mixed $value): void
    {
        if (is_string($value)) {
            if (preg_match('/^([a-zA-Z0-9\-]+): (.+)$/', $value, $matches)) {
                $value = new HeaderDescriptor($matches[1], $matches[2]);
            } else {
                throw new InvalidArgumentException('El valor debe ser un array o una cadena con el formato "nombre: valor"');
            }
        }
        $value = self::cast($value);
        if ($value instanceof HeaderDescriptor) {
            if (isset($this->values[$value->name])) {
                $this->values[$value->name]->set('value', $value->value);
            } else {
                $this->values[$value->name] = $value;
            }
        }
        //  parent::add($value);
    }

    public function getHeader(string $name): ?HeaderDescriptor
    {
        return $this->get($name);
    }

    public function getHeaderString(string $name): ?string
    {
        return (string)$this->get($name);
    }

    public function getHeaders(): array
    {
        $headers = $this->toArray();
        array_walk($headers, static fn(&$value) => $value = (string)$value);
        return $headers;
    }

    public function getHeadersString(): string
    {
        return implode("\r\n", $this->getHeaders());
    }

    public function getHeaderNames(): array
    {
        return $this->keys();
    }
}