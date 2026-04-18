<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Managers\Pubsub\PayloadDescriptor;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Base;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;

class PublishDelivery extends Base
{
    protected(set) PublishDescriptor $response;

    protected null|string $idProperty {
        get {
            return
                'deliveryId';
        }
    }
    protected(set) string $deliveryId;

    public function initialize(Status $status, ?array &$values): void
    {
       $values['action'] = 'message';
       $values['response'] = new PublishDescriptor($values);
    }
}