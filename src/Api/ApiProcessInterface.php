<?php

namespace Tabula17\Satelles\Utilis\Api;

use Tabula17\Satelles\Utilis\Config\ApiPathConfig;
use Tabula17\Satelles\Utilis\Utilities\Request;

interface ApiProcessInterface
{
    public function process(ApiPathConfig $api, Request $payload): ApiProcessResult;
}