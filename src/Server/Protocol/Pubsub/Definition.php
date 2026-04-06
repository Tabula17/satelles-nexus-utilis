<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Action;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ServiceProtocol;

class Definition extends Action
{
    const ServiceProtocol PROTOCOL = ServiceProtocol::PUBSUB;
    /**
     * ws.on('message', (data) => {
     * const { action, topic, message } = JSON.parse(data);
     * if (action === 'subscribe') { } else if (action === 'publish') { }});
     */
    public string $publish;
    public string $subscribe;
    public string $unsubscribe;

}