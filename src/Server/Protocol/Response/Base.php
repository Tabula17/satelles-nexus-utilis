<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response;

use Tabula17\Satelles\Nexus\Utilis\Exception\UnexpectedValueException;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Data\Stats;
use Tabula17\Satelles\Utilis\Collection\CallableCollection;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class Base extends AbstractDescriptor implements ResponseInterface
{
    protected(set) string $id;
    protected(set) string $type {
        set(string $type) {
            $types = $this->protocol instanceof Type ? $this->protocol->toArray() : $this->protocol;
            if (!in_array($type, $types)) {
                throw new UnexpectedValueException('Invalid type: ' . $type . '. Must be one of: ' . implode(', ', $types));
            }
            $this->type = $type;
        }
    }

    protected(set) ?Stats $_metadata {
        set(Stats|array|null $stats) {
            $stats = is_array($stats) ? new Stats($stats) : $stats;
            $this->_metadata = $stats;
        }
    }

    /*
    protected(set) ?string $message;
    protected(set) string $status;
    protected(set) ?string $token;
    */
    private CallableCollection $validators;

    public function __construct(
        ?array                      $values = [],
        private readonly array|Type $protocol = new Type()
    )
    {
        $this->validators = new CallableCollection();
        if (empty($values)) {
            $values = [];
        }
        if (!isset($values['id'])) {
            $prefix = "Fxs::" . ucfirst($values['type']);
            $values['id'] = uniqid($prefix, false);
        }
        parent::__construct($values);
    }

    public function addValidator(callable $validator): void
    {
        $this->validators->addIfNotExist($validator);
    }

    public function removeValidator(callable $validator): void
    {
        $this->validators->remove($validator);
    }

    final public function isValid(): bool
    {
        foreach ($this->validators as $validator) {
            if (!$validator($this)) {
                return false;
            }
        }
        return true;
    }
}