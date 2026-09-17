<?php

namespace Tabula17\Satelles\Utilis\Api;

use Tabula17\Satelles\Utilis\File\MimeTypes;

abstract class ApiProcessResult
{
    protected(set) MimeTypes $outputType = MimeTypes::JSON;
    abstract public function getOutput(): mixed;
    abstract public function halt(): bool;
}