<?php

use Carbon\CarbonPeriod;
use Finller\Kpi\Kpi;
use Finller\Kpi\KpiBuilder;
use Finller\Kpi\Support\KpiCollection;

$supportedIntervals = ['day', 'week', 'month', 'year'];

it('can query kpis by key', function ($interval) {
    $key_1 = "test:KpiBuilder:queryKpiBykey:{$interval}:1";
    $key_2 = "test:KpiBuilder:queryKpiBykey:{$interval}:2";

    $intervalLength = 10;

    $startData = now()->startOfDay()->sub($interval, $intervalLength);
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

    $period = CarbonPeriod::between($startData, $endData)->interval("1 {$interval}");

    expect(KpiBuilder::query($key_1)->between($startData, $endData)->count())->toBe($period->count());
    expect(KpiBuilder::query($key_2)->between($startData, $endData)->count())->toBe($period->count());
})->with($supportedIntervals);

it('can query kpis on a specific period', function ($interval) {
    $key = "test:KpiBuilder:queryOnPeriod:{$interval}";

    $intervalLength = 5;

    // create larger data window than queried window
    $startData = now()->startOfDay()->sub($interval, $intervalLength + 2);
    $endData = now()->startOfDay()->add($interval, $intervalLength + 2);

    $kpis = Kpi::factory([
        'key' => $key,
    ])->number()->between($startData, $endData, $interval)->create();

    $startQuery = now()->startOfDay()->sub($interval, $intervalLength);
    $endQuery = now()->startOfDay();

    $periodQueried = CarbonPeriod::between($startQuery, $endQuery)->interval("1 {$interval}");

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

it('can query kpis with gaps filled on period', function ($interval) {
    $key = "test:KpiBuilder:fillGaps:{$interval}";
    $start = now()->startOfDay()->sub($interval, 10);
    $end = now()->startOfDay();

    /** @var KpiCollection $kpis */
    $kpis = Kpi::factory([
        'key' => $key,
    ])->number()->between(
        $start,
        $end,
        $interval
    )->create();

    $period = CarbonPeriod::between($start, $end)->interval("1 {$interval}");
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
