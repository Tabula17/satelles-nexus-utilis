<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Rpc;

use Tabula17\Satelles\Nexus\Utilis\Server\Processor\EndpointProcessorInterface;
use Tabula17\Satelles\Nexus\Utilis\Server\Processor\MethodPublisherInterface;

interface RpcProcessorInterface extends EndpointProcessorInterface, MethodPublisherInterface
{

}