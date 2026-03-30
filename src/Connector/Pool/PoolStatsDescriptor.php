<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Pool;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class PoolStatsDescriptor extends AbstractDescriptor
{
    /*
     * [
            'name' => $this->name,
            'id' => $this->id,
            'config' => $this->config->getSafeData(),
            'delay' => $this->config->dealy ?? 0,
            'poolSize' => $this->poolSize,
            'used' => $this->used,
            'available' => $this->available(),
            'status' => $this->status->value,
            'poolClass' => $this->poolClass,
            'lastError' => $this->status->hasFailure() ? [
                'message' => $this->lastError,
                'time' => DateTime::createFromFormat('U.u', sprintf('%f', $this->lastErrorAt))->format('Y-m-d H:i:s.u'),
                'attempts' => $this->failedAttempts
            ]
     */
    protected(set) string $name;
    protected(set) ?string $id;
    protected(set) array $config;
    protected(set) float $delay;
    protected(set) int $poolSize;
    protected(set) int $used;
    protected(set) bool $available;
    protected(set) string $status;
    protected(set) string $poolClass;
    protected(set) ?array $lastError;
    protected(set) string $checkedOn;
}