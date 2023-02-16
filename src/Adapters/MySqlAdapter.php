<?php

namespace Finller\Kpi\Adapters;

use Exception;
use Illuminate\Support\Facades\DB;

class MySqlAdapter extends AbstractAdapter
{
    public function groupBy(string $column, string $interval)
    {
        return match ($interval) {
            'day' => DB::raw("DAY({$column}), MONTH({$column}), YEAR({$column})"),
            'week' => DB::raw("WEEK({$column}), MONTH({$column}), YEAR({$column})"),
            'month' => DB::raw("MONTH({$column}), YEAR({$column})"),
            'year' => DB::raw("YEAR({$column})"),
            default => throw new Exception("Interval '$interval' value not supported")
        };
    }
}
