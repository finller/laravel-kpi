<?php

namespace Finller\Kpi\Adapters;

abstract class AbstractAdapter
{
    // Add return type when dropping Laravel 9.0
    abstract public function groupBy(string $column, string $interval);
}
