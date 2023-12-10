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
     *
     * @return Collection<string, callable|Kpi>
     */
    public static function registerDefaultKpis(?Carbon $date = null): Collection
    {
        $model = config('kpi.kpi_model');

        /** @var Builder $query */
        $query = static::query();
        $query->when($date, fn (Builder $q) => $q->whereDate('created_at', '<=', $date->clone()));

        return collect()
            ->put('count', new $model([
                'number_value' => $query->clone()->count(),
                'created_at' => $date?->clone(),
            ]));
    }

    /**
     * When providing a date, the snapshot only take data prior to this date
     * Just like if you would have taken the snapshot in the past.
     *
     * @param  null|string[]  $only Array of kpi keys to snapshot
     */
    public static function snapshotKpis(?Carbon $date = null, ?array $only = null, ?array $except = null): Collection
    {
        $kpis = static::registerDefaultKpis($date);

        if (method_exists(static::class, 'registerKpis')) {
            $kpis = $kpis->merge(static::registerKpis($date));
        }

        $namespace = static::getKpiNamespace();

        return $kpis
            ->only($only)
            ->except($except)
            ->map(function (callable|Kpi $item, $key) use ($namespace) {
                $kpi = value($item);

                $kpi->key ??= "{$namespace}:{$key}";
                $kpi->created_at ??= now();
                $kpi->save();

                return $kpi;
            });
    }

    /**
     * @param  Carbon[]  $exceptDates
     * @param  null|string[]  $onlyKeys Kpi keys to snapshot
     * @param  null|string[]  $exceptKeys Kpi keys not to snapshot
     * @return Collection<int, Kpi>
     */
    public static function backfillKpis(
        Carbon $start,
        Carbon $end,
        ?KpiInterval $interval = KpiInterval::Day,
        ?array $onlyKeys = null,
        ?array $exceptKeys = null,
        array $exceptDates = [],
    ): Collection {
        $kpis = collect();

        $exceptDates = array_map(fn (Carbon $date) => $date->format($interval->dateFormatComparator()), $exceptDates);

        $date = $start->clone();

        while ($date->lessThanOrEqualTo($end)) {
            if (! in_array($date->format($interval->dateFormatComparator()), $exceptDates)) {
                $kpis->push(...static::snapshotKpis($date, $onlyKeys, $exceptKeys));
            }

            $date->add($interval->value, 1);
        }

        return $kpis;
    }
}
