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

        parent::__construct();
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

    /**
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function load(array $headers): void
    {
        foreach ($headers as $header) {
            $this->add($header);
        }

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
        $headers = array_map(function ($value) {
            return $value->get('value');
        }, $this->values);
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