<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Type\StatusDescriptor;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status as ProtocolStatus;

class Status extends Base
{
    protected(set) StatusDescriptor $response;

    public function __construct(?ProtocolStatus $status = null, ?array $values = [])
    {
        $statusDesc = new StatusDescriptor($status ?? ProtocolStatus::unknown);
        $statusDesc->loadProperties($values);
        $values['response'] = $statusDesc;
        parent::__construct($values);
    }
}