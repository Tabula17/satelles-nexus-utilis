<?php

namespace Tabula17\Satelles\Utilis\Server\Protocol\Rpc;

use Tabula17\Satelles\Utilis\Server\Processor\EndpointProcessorInterface;
use Tabula17\Satelles\Utilis\Server\Processor\MethodPublisherInterface;

interface RpcProcessorInterface extends EndpointProcessorInterface, MethodPublisherInterface
{

}