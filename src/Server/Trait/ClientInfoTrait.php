<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Trait;

use Swoole\Table;

trait ClientInfoTrait
{
    final public const bool CLIENT_INFO_ENABLED = true;
    private Table $clientInfoTable;


    private function initClientInfoTable(): void
    {
        $this->clientInfoTable = new Table(2048);
        $this->clientInfoTable->column('fd', Table::TYPE_INT, 8);
        $this->clientInfoTable->column('ip', Table::TYPE_STRING, 16);
        $this->clientInfoTable->column('id', Table::TYPE_INT, 8);
        $this->clientInfoTable->column('username', Table::TYPE_STRING, 255);
        $this->clientInfoTable->column('token', Table::TYPE_STRING, 255);
        $this->clientInfoTable->create();
    }

    abstract public function getClientData(int $fd): array;

    private function setClientInfo(int $fd, array $data): void
    {
        $data = array_merge(
            [
                'fd' => $fd,
                'ip' => '0.0.0.0',
                'id' => 0,
                'username' => 'guest',
                'token' => '',
            ], $data);
        $this->clientInfoTable->set($fd, $data);
    }

    private function deleteClientInfo(int $fd): void
    {
        $this->clientInfoTable->del($fd);
    }

    private function updateClientInfo(int $fd, array $data): void
    {
        if ($this->hasClientInfo($fd)) {
            $data = array_merge($this->clientInfoTable->get($fd), $data);
            $this->clientInfoTable->set($fd, $data);
        }
    }

    private function hasClientInfo(int $fd): bool
    {
        return $this->clientInfoTable->exists($fd);
    }

    private function getAllClientInfo(): array
    {
        $clientInfo = [];
        foreach ($this->clientInfoTable as $fd => $info) {
            $clientInfo[$fd] = $info;
        }
        return $clientInfo;
    }

}