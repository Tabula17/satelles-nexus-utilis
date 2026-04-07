<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Type\StatusDescriptor;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status ;

class StatusResponse extends Base
{
    protected(set) StatusDescriptor $response;

    protected null|string $idProperty {
        get {
            return
                'payloadId';
        }
    }
    protected(set) string $payloadId;


    /*public function __construct(?Status $status = null, ?array $values = [])
    {
        $statusDesc = new StatusDescriptor($status ?? Status::unknown);
        $statusDesc->loadProperties($values);
        $values['response'] = $statusDesc;
        parent::__construct($values);
    }*/

    public function initialize(Status $status, ?array &$values): void
    {
        $statusDesc = new StatusDescriptor($status ?? Status::unknown);
        $statusDesc->loadProperties($values);
        $values['response'] = $statusDesc;
    }
}