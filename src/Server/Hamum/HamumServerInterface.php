<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Hamum;

interface HamumServerInterface
{
    final const string HAMUM_VERSION = '0.0.1';
    const HamumTypes TYPE = HamumTypes::TCP;

    public function isHamumEnabled(): bool;

    public function isCronosEnabled(): bool;

    public function isProcessSubscriberEnabled(): bool;

    public function isClientInfoEnabled(): bool;
}