<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Type;


use InvalidArgumentException;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class MessageDescriptor extends AbstractDescriptor
{
    protected(set) ?string $message;
    protected(set) ?string $code;
    protected(set) ?string $error;

    public function __construct(array $values = [])
    {
        if(!isset($values['message'])&&!isset($values['error'])) {
            throw new InvalidArgumentException('MessageDescriptor must have a message or an error');
        }
        parent::__construct($values);
    }

}