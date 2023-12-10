<?php

use Carbon\CarbonPeriod;
use Finller\Kpi\Enums\KpiInterval;
use Finller\Kpi\Kpi;
use Finller\Kpi\KpiBuilder;
use Finller\Kpi\Support\KpiCollection;

$supportedIntervals = KpiInterval::cases();

it('can query kpis by key', function (KpiInterval $interval) {
    $key_1 = "test:KpiBuilder:queryKpiBykey:{$interval->value}:1";
    $key_2 = "test:KpiBuilder:queryKpiBykey:{$interval->value}:2";

    $intervalLength = 10;

    $startData = now()->startOfDay()->sub($interval->value, $intervalLength);
    $endData = now()->startOfDay();

    Kpi::factory([
        'key' => $key_1,
    ])->number()->between(
        $startData,
        $endData,
        $interval
    )->create();

    Kpi::factory([
        'key' => $key_2,
    ])->number()->between(
        $startData,
        $endData,
        $interval
    )->create();

    $period = CarbonPeriod::between($startData, $endData)->interval("1 {$interval->value}");

    expect(KpiBuilder::query($key_1)->between($startData, $endData)->count())->toBe($period->count());
    expect(KpiBuilder::query($key_2)->between($startData, $endData)->count())->toBe($period->count());
})->with($supportedIntervals);

it('can query kpis on a specific period', function (KpiInterval $interval) {
    $key = "test:KpiBuilder:queryOnPeriod:{$interval->value}";

    $intervalLength = 5;

    // create larger data window than queried window
    $startData = now()->startOfDay()->sub($interval->value, $intervalLength + 2);
    $endData = now()->startOfDay()->add($interval->value, $intervalLength + 2);

    $kpis = Kpi::factory([
        'key' => $key,
    ])
        ->number()
        ->between($startData, $endData, $interval)
        ->create();

    $startQuery = now()->startOfDay()->sub($interval->value, $intervalLength);
    $endQuery = now()->startOfDay();

    $periodQueried = CarbonPeriod::between($startQuery, $endQuery)->interval("1 {$interval->value}");

    $kpisOnInterval = KpiBuilder::query(
        Kpi::query()->where('key', $key)
    )
        ->between($startQuery, $endQuery)
        ->perInterval($interval)
        ->get();

    expect($kpisOnInterval)->toHaveCount($periodQueried->count());

    /** @var Kpi */
    $firstKpi = $kpisOnInterval->first();
    $lastKpi = $kpisOnInterval->last();

    expect($firstKpi?->created_at->isSameDay($startQuery));
    expect($lastKpi?->created_at->isSameDay($endQuery));
})->with($supportedIntervals);

it('can query kpis with gaps filled on period', function (KpiInterval $interval) {
    $key = "test:KpiBuilder:fillGaps:{$interval->value}";
    $start = now()->startOfDay()->sub($interval->value, 10);
    $end = now()->startOfDay();

    /** @var KpiCollection $kpis */
    $kpis = Kpi::factory([
        'key' => $key,
    ])->number()->between(
        $start,
        $end,
        $interval
    )->create();

    $period = CarbonPeriod::between($start, $end)->interval("1 {$interval->value}");
    $expectedKpisCount = $period->count();

    expect($kpis)->toHaveCount($expectedKpisCount);

    // delete random kpis
    $deleted = [$kpis->first()->delete(), $kpis->get(1)->delete(), $kpis->get(6)->delete(), $kpis->last()->delete()];

    $remainingKpisWithGapsFilled = KpiBuilder::query(
        Kpi::query()->where('key', $key)
    )
        ->between($start, $end)
        ->perInterval($interval)
        ->fillGaps()
        ->get();

    expect($remainingKpisWithGapsFilled)->toHaveCount($expectedKpisCount);
})->with($supportedIntervals);

it('can query kpis as relative on period', function (KpiInterval $interval) {
    $key = "test:KpiBuilder:toRelative:{$interval->value}";
    $end = now()->startOfDay();
    $start = $end->clone()->sub($interval->value, 10);

    $period = CarbonPeriod::between($start, $end)->interval("1 {$interval->value}");

    /** @var KpiCollection $kpis */
    $seededKpis = Kpi::factory([
        'key' => $key,
    ])->number()->between(
        $start->sub($interval->value, 1), // because of relative query, we need to seed 1 internal before the start of the query
        $end,
        $interval
    )->create();

    $kpis = KpiBuilder::query(
        Kpi::query()->where('key', $key)
    )
        ->between($start, $end)
        ->perInterval($interval)
        ->relative()
        ->get();

    expect($kpis)->toHaveCount($period->count());

    expect($kpis->get(1)->number_value)
        ->toBe($seededKpis->get(2)->number_value - $seededKpis->get(1)->number_value);

    expect($kpis->last()->number_value)
        ->toBe($seededKpis->last()->number_value - $seededKpis->get($seededKpis->count() - 2)->number_value);
})->with($supportedIntervals);
