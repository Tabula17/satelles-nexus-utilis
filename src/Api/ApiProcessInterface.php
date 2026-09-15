<?php

namespace Tabula17\Satelles\Utilis\Api;

use Tabula17\Satelles\Utilis\Config\ApiPathConfig;

interface ApiProcessInterface
{
    public function process(ApiPathConfig $api): ApiProcessResult;
}