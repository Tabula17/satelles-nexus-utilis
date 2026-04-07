<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub\PayloadDescriptor;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Base;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;

class PublishDelivery extends Base
{
    protected(set) TopicDescriptor $response;

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
       $values['response'] = new TopicDescriptor($values);
    }
}