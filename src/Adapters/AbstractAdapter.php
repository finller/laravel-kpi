<?php

namespace Finller\Kpi\Adapters;

abstract class AbstractAdapter
{
    abstract public function groupBy(string $column, string $interval): \Illuminate\Database\Query\Expression;
}