<?php

namespace Tabula17\Satelles\Nexus\Utilis\Connector\Database;

abstract class AbstractDriver implements DriverInterface
{
    public readonly DbConfig $config;
    protected(set) bool $autoClose = true;
    protected(set) bool $autoCommit = true;

}