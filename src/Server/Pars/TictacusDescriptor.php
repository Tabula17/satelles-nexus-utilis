<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Pars;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class TictacusDescriptor extends AbstractDescriptor
{
    protected(set) int $id;
    protected(set) int $interval
        {
            set(int $interval) {
                if ($interval < 1) {
                    trigger_error('Interval must be a positive integer.', E_USER_WARNING);
                    $interval = 1;
                }
                $this->interval = $interval;
            }
        }
    protected(set) string $owner = 'Tictacus';
    protected(set) ?string $name;
    protected(set) ?string $description;
    protected(set) float $added;

}