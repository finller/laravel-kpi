<?php

namespace Finller\Kpi;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Finller\Kpi\Enums\KpiInterval;

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

    public static function snapshotKpiCount()
    {
        $model = config('kpi.kpi_model');

        return $model::create([
            'key' => static::getKpiNamespace() . ':count',
            'number_value' => static::count(),
        ]);
    }

    public static function backfillKpi(
        callable $callback,
        ?KpiInterval $interval = KpiInterval::Day,
        ?string $column = 'created_at',
        ?Carbon $start = null,
        ?Carbon $end = null,
        ?string $key = 'count',
    ): array {
        $kpiModel = config('kpi.kpi_model');
        /** @var ?Carbon */
        $start = $start ?? Carbon::parse(static::min($column));
        /** @var ?Carbon */
        $end = $end ?? Carbon::parse(static::max($column));
        /** @var string */
        $key = static::getKpiNamespace() . ':' . $key;
        /** @var Carbon[] */
        $fillDates = $kpiModel::query()
            ->where('key', $key)
            ->perInterval($interval)
            ->between($start, $end)
            ->get()
            ->fillGaps($start, $end, $interval, [
                'key' => $key,
                'number_value' => 0
            ])
            ->toArray();

        foreach ($fillDates as $k => $date) {
            if (isset($date['id'])) continue;

            $kpi = new $kpiModel();

            $kpi->fill(call_user_func(
                $callback,
                static::query(),
                $start,
                $end,
                $key,
                $date
            ));

            $kpi->save();

            $fillDates[$k] = $kpi->toArray();
        }
        /** @return array */
        return $fillDates;
    }

    public static function backfillKpiCount(
        ?KpiInterval $interval = KpiInterval::Day,
        ?string $column = 'created_at',
        ?Carbon $start = null,
        ?Carbon $end = null,
        ?string $key = 'count'
    ): array {
        return static::backfillKpi(
            function ($model, $start, $end, $key, $date) use ($column) {
                $count = $model->whereBetween($column, [
                    $start,
                    Carbon::parse($date['created_at'])->endOfDay(),
                ])
                    ->count();

                return [
                    'key' => $key,
                    'number_value' => $count,
                    'created_at' => $date['created_at'],
                ];
            },
            $interval,
            $column,
            $start,
            $end,
            $key,
        );
    }
}
