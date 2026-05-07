<?php
include_once __DIR__ . '/../../../../vendor/autoload.php';

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer\Definition;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\FileTransfer\FileTransferActionsEnum;

$fileAction = new Definition();

var_dump(FileTransferActionsEnum::propertiesMap(), $fileAction->toArray());

