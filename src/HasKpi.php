<?php

namespace Finller\Kpi;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasKpi
{
    public static function getKpiNamespace(): string
    {
        return Str::lower(Str::plural(class_basename(static::class)));
    }

    public static function kpi(string $key = 'count'): KpiBuilder
    {
        $namespace = static::getKpiNamespace();

        return KpiBuilder::query("{$namespace}:{$key}");
    }

    public static function snapshotKpiCount(Carbon $date = null)
    {
        $model = config('kpi.kpi_model');

        /** @var Builder $query */
        $query = static::query()
            ->when($date, fn (Builder $q) => $q->whereDate('created_at', '<=', $date));

        return $model::create([
            'key' => static::getKpiNamespace().':count',
            'number_value' => $query->count(),
        ]);
    }
}
