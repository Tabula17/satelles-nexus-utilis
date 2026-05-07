<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request\Action;

/**
 * @deprecated Parte de FileTransferProtocol
 */
class Definition extends Action
{
    private array $properties;
    public function __construct()
    {
        $this->properties = FileTransferActionsEnum::toArray();
        parent::__construct();
    }
    public function toArray(): array
    {
        return $this->properties;
    }

    public function __get(string $name)
    {
        return $this->properties[$name] ?? null;
    }
    public function __isset(string $name): bool
    {
        return isset($this->properties[$name]);
    }

}