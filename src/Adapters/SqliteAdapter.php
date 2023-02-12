<?php

namespace Finller\Kpi\Adapters;

use Exception;
use Illuminate\Support\Facades\DB;

class SqliteAdapter extends AbstractAdapter
{
    public function groupBy(string $column, string $interval): \Illuminate\Database\Query\Expression
    {
        return match ($interval) {
            'day' => DB::raw("strftime('%Y-%m-%d', {$column})"),
            "week" => DB::raw("strftime('%Y-%W', {$column})"),
            "month" => DB::raw("strftime('%Y-%m', {$column})"),
            "year" => DB::raw("strftime('%Y', {$column})"),
            default => throw new Exception("Interval '$interval' value not supported")
        };
    }
}
