<?php

namespace Finller\Kpi;

use Carbon\Carbon;
use Finller\Kpi\Enums\KpiInterval;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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

    /**
     * The date represent the date of the KPI
     */
    public static function registerDefaultKpis(Carbon $date = null): Collection
    {
        $model = config('kpi.kpi_model');

        /** @var Builder $query */
        $query = static::query();
        $query->when($date, fn (Builder $q) => $q->whereDate('created_at', '<=', $date->clone()));

        return collect()
            ->push(new $model([
                'key' => static::getKpiNamespace().':count',
                'number_value' => $query->clone()->count(),
                'created_at' => $date?->clone() ?? now(),
            ]));
    }

    /**
     * When providing a date, the snapshot only take data prior to this date
     * Just like if you would have taken the snapshot in the past.
     */
    public static function snapshotKpis(Carbon $date = null): Collection
    {
        $kpis = static::registerDefaultKpis($date);

        if (method_exists(static::class, 'registerKpis')) {
            $kpis->push(...static::registerKpis($date));
        }

        return $kpis->map(function (Kpi $kpi) {
            $kpi->created_at ??= now();
            $kpi->save();

            return $kpi;
        });
    }

    /**
     * @param  Carbon[]  $except
     * @return Collection<int, Kpi>
     */
    public static function backfillKpis(
        Carbon $start,
        Carbon $end,
        ?KpiInterval $interval = KpiInterval::Day,
        array $except = [],
    ): Collection {
        $kpis = collect();

        $except = array_map(fn (Carbon $date) => $date->format($interval->dateFormatComparator()), $except);

        $date = $start->clone();

        while ($date->lessThanOrEqualTo($end)) {
            if (! in_array($date->format($interval->dateFormatComparator()), $except)) {
                $kpis->push(...static::snapshotKpis($date));
            }

            $date->add($interval->value, 1);
        }

        return $kpis;
    }
}
