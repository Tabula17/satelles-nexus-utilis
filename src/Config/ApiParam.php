<?php

namespace Tabula17\Satelles\Utilis\Config;

use Tabula17\Satelles\Utilis\Config\BaseParamConfig;

class ApiParam extends BaseParamConfig
{
    protected(set) bool $pathParam = false
        {
            set {
                $this->pathParam = $value;
                if ($this->queryParam === $value) {
                    $this->queryParam = !$value;
                }
            }
        }
    protected(set) bool $pathWithKey = false;
    protected(set) bool $queryParam = true
        {
            set {
                $this->queryParam = $value;
                if ($this->pathParam === $value) {
                    $this->pathParam = !$value;
                }
            }
        }
}