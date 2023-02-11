<?php

use Carbon\CarbonPeriod;
use Finller\Kpi\Kpi;
use Finller\Kpi\Support\KpiCollection;

$supportedIntervals = ['day', 'week', 'month', 'year'];

it('can store number value', function () {
    /** @var Kpi */
    $kpi = Kpi::factory()->number()->create();
    expect(Kpi::query()->where('number_value', $kpi->number_value)->find($kpi->id))->toBeInstanceOf(Kpi::class);
});

it('can store string value', function () {
    /** @var Kpi */
    $kpi = Kpi::factory()->string()->create();
    expect(Kpi::query()->where('string_value', $kpi->string_value)->find($kpi->id))->toBeInstanceOf(Kpi::class);
});

it('can store json value', function () {
    /** @var Kpi */
    $kpi = Kpi::factory()->json()->create();
    expect(Kpi::query()->whereNotNull('json_value')->find($kpi->id))->toBeInstanceOf(Kpi::class);
});

it('can store money value', function () {
    /** @var Kpi */
    $kpi = Kpi::factory()->money()->create();
    expect(
        Kpi::query()
            ->where('money_value', $kpi->money_value)
            ->where('money_currency', $kpi->money_currency)
            ->find($kpi->id)
    )->toBeInstanceOf(Kpi::class);
});

it('can query kpis per interval', function ($interval) {
    $key = "test:query:{$interval}";

    Kpi::factory([
        'key' => $key,
    ])->number()->between(
        $start = now()->startOfDay()->subMonth(),
        $end = now()->startOfDay(),
        $interval
    )->create();

    expect(
        Kpi::query()->where('key', $key)->count()
    )->toBe(
        CarbonPeriod::between($start, $end)->interval("1 {$interval}")->count()
    );
})->with($supportedIntervals);

it('can fill gaps between intervals', function ($interval) {
    $key = "test:fillGaps:{$interval}";
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

    /** @var KpiCollection $remainingKpis */
    $remainingKpis = Kpi::query()->where('key', $key)->get();

    expect($remainingKpis)->toHaveCount($expectedKpisCount - count($deleted));

    $remainingKpisWithGapsFilled = $remainingKpis->fillGaps($start, $end, $interval);

    expect($remainingKpisWithGapsFilled)->toHaveCount($expectedKpisCount);
})->with($supportedIntervals);

it('can fill gaps between intervals by guessing parameters', function ($interval) {
    $key = "test:fillGapsByGuessing:{$interval}";
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

    // delete kpis
    $deleted = [$kpis->get(2)->delete(), $kpis->get(4)->delete()];

    /** @var KpiCollection $remainingKpis */
    $remainingKpis = Kpi::query()->where('key', $key)->get();

    expect($remainingKpis)->toHaveCount($expectedKpisCount - count($deleted));

    $remainingKpisWithGapsFilled = $remainingKpis->fillGaps();

    expect($remainingKpisWithGapsFilled)->toHaveCount($expectedKpisCount);
})->with($supportedIntervals);

it('can fill gaps between intervals with sibling value', function ($interval) {
    $key = "test:fillGapsWithSibling:{$interval}";
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

    // delete kpis
    $deleted = [$kpis->first()->delete(), $kpis->get(5)->delete(), $kpis->last()->delete()];

    /** @var KpiCollection $remainingKpis */
    $remainingKpis = Kpi::query()->where('key', $key)->get();

    expect($remainingKpis)->toHaveCount($expectedKpisCount - count($deleted));

    $remainingKpisWithGapsFilled = $remainingKpis->fillGaps($start, $end, $interval);

    expect($remainingKpisWithGapsFilled)->toHaveCount($expectedKpisCount);

    // First empty gap gets the value of the first next available kpi
    expect($remainingKpisWithGapsFilled->first()->number_value)
        ->toBe($kpis->get(1)->number_value);
    // Last empty gap gets the value of the last available kpi
    expect($remainingKpisWithGapsFilled->last()->number_value)
        ->toBe($kpis->get($expectedKpisCount - 2)->number_value);

    // Empty gaps get the value of the previous available kpi
    expect($remainingKpisWithGapsFilled->get(5)->number_value)
        ->toBe($kpis->get(4)->number_value);
})->with(['day']);
