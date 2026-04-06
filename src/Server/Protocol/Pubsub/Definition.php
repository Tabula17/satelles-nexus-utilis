<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Pubsub;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Action;

class Definition extends Action
{
    /**
     * ws.on('message', (data) => {
     * const { action, topic, message } = JSON.parse(data);
     * if (action === 'subscribe') { } else if (action === 'publish') { }});
     */
    public string $publish;
    public string $subscribe;
    public string $unsubscribe;

}