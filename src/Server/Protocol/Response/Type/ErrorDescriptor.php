<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Type;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class ErrorDescriptor extends AbstractDescriptor
{
    protected(set) int $code;
    protected(set) string $message;
    protected(set) ?array $data;

}