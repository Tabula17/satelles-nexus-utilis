<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request;

use Tabula17\Satelles\Nexus\Utilis\Exception\UnexpectedValueException;
use Tabula17\Satelles\Nexus\Utilis\Server\Hamum\HamumServerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\ProtocolManagerInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class Base extends AbstractDescriptor implements RequestHandlerInterface
{

    protected(set) string $action {
        set(string $action) {
            $actions = $this->protocol instanceof Action ? $this->protocol->toArray() : $this->protocol;
            if (!in_array($action, $actions)) {
                throw new UnexpectedValueException('Invalid action: ' . $action . '. Must be one of: ' . implode(', ', $actions));
            }
            $this->action = $action;
        }
    }
    public function __construct(
        ?array                                    $values = [],
        private readonly array|Action $protocol = new Action()
    )
    {
        parent::__construct($values);
    }

    public function handle(int $fd, HamumServerInterface $server, ProtocolManagerInterface $protocolManager): Status
    {
        return Status::unknown;
    }
}