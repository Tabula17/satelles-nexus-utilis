<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Type;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class StatusDescriptor extends AbstractDescriptor
{
    protected(set) Status $status;
    protected(set) ?string $message;
    protected(set) ?string $code;
    protected(set) ?string $error;

    public function __construct(Status $status)
    {
        $this->status = $status;
        parent::__construct();
    }
}