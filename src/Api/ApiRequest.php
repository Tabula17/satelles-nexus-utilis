<?php

namespace Tabula17\Satelles\Utilis\Api;


use Tabula17\Satelles\Utilis\Config\ApiPathConfig;

class ApiRequest
{
    public ApiProcessorsCollection $processors;
    public function __construct(public readonly ApiPathConfig $apiPathConfig)
    {
    }

}