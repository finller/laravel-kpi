<?php

namespace Finller\Kpi;

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

    public static function snapshotKpiCount(): Kpi
    {
        return Kpi::create([
            'key' => static::getKpiNamespace() . ':count',
            'number_value' => static::count(),
        ]);
    }
}
