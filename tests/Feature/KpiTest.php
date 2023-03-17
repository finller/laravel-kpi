<?php

use Carbon\CarbonPeriod;
use Finller\Kpi\Enums\KpiInterval;
use Finller\Kpi\Kpi;
use Finller\Kpi\Support\KpiCollection;

$supportedIntervals = KpiInterval::cases();

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

it('can seed kpis on interval', function (KpiInterval $interval) {
    $key = "test:seedKpis:{$interval->value}";

    $intervalLength = 10;

    $startData = now()->startOfDay()->sub($interval->value, $intervalLength);
    $endData = now()->startOfDay();

    $kpis = Kpi::factory([
        'key' => $key,
    ])->number()->between(
        $startData,
        $endData,
        $interval
    )->create();

    $period = CarbonPeriod::between($startData, $endData)->interval("1 {$interval->value}");

    expect($kpis)->toHaveCount($period->count());
    expect(Kpi::query()->where('key', $key)->count())->toBe($period->count());
})->with($supportedIntervals);

it('can query kpis by key', function (KpiInterval $interval) {
    $key_1 = "test:queryKpiBykey:{$interval->value}:1";
    $key_2 = "test:queryKpiBykey:{$interval->value}:2";

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

    expect(Kpi::query()->where('key', $key_1)->count())->toBe($period->count());
    expect(Kpi::query()->where('key', $key_2)->count())->toBe($period->count());
})->with($supportedIntervals);

it('can query kpis by key when grouping by interval', function (KpiInterval $interval) {
    $key_1 = "test:queryKpiBykeyGroupingByInterval:{$interval->value}:1";
    $key_2 = "test:queryKpiBykeyGroupingByInterval:{$interval->value}:2";

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

    expect(Kpi::query()->where('key', $key_1)->perInterval($interval)->count())->toBe($period->count());
    expect(Kpi::query()->where('kpis.key', $key_2)->perInterval($interval)->count())->toBe($period->count());
})->with($supportedIntervals);

it('can query kpis on a specific period', function ($interval) {
    $key = "test:queryOnPeriod:{$interval->value}";

    $intervalLength = 10;

    // create larger data window than queried window
    $startData = now()->startOfDay()->sub($interval->value, $intervalLength + 2);
    $endData = now()->startOfDay()->add($interval->value, $intervalLength + 2);

    $kpis = Kpi::factory([
        'key' => $key,
    ])->number()->between($startData, $endData, $interval)->create();

    $startQuery = now()->startOfDay()->sub($interval->value, $intervalLength);
    $endQuery = now()->startOfDay();

    $periodQueried = CarbonPeriod::between($startQuery, $endQuery)->interval("1 {$interval->value}");

    /** @var KpiCollection */
    $kpisOnInterval = Kpi::query()
        ->where('key', $key)
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

it('query only the latest kpi for each interval', function ($interval) {
    $key = "test:queryTheLatestOnPeriod:{$interval->value}";

    $intervalLength = 5;

    $start = now()->startOfDay()->sub($interval->value, $intervalLength);
    $end = now()->startOfDay();
    $periodQueried = CarbonPeriod::between($start, $end)->interval("1 {$interval->value}");

    // Seed data twice a day
    Kpi::factory([
        'key' => $key,
    ])->number()->between($start, $end, $interval)->create();
    Kpi::factory([
        'key' => $key,
    ])->number()->between($start, $end, $interval)->create();

    /** @var KpiCollection */
    $kpisOnInterval = Kpi::query()
        ->where('key', $key)
        ->between($start, $end)
        ->perInterval($interval)
        ->get();

    expect($kpisOnInterval)->toHaveCount($periodQueried->count());
})->with($supportedIntervals);

it('can fill gaps between intervals', function (KpiInterval $interval) {
    $key = "test:fillGaps:{$interval->value}";
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

    /** @var KpiCollection $remainingKpis */
    $remainingKpis = Kpi::query()->where('key', $key)->get();

    expect($remainingKpis)->toHaveCount($expectedKpisCount - count($deleted));

    $remainingKpisWithGapsFilled = $remainingKpis->fillGaps($start, $end, $interval);

    expect($remainingKpisWithGapsFilled)->toHaveCount($expectedKpisCount);
})->with($supportedIntervals);

it('can fill gaps between intervals by guessing parameters', function ($interval) {
    $key = "test:fillGapsByGuessing:{$interval->value}";
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

    // delete kpis
    $deleted = [$kpis->get(2)->delete(), $kpis->get(4)->delete()];

    /** @var KpiCollection $remainingKpis */
    $remainingKpis = Kpi::query()->where('key', $key)->get();

    expect($remainingKpis)->toHaveCount($expectedKpisCount - count($deleted));

    $remainingKpisWithGapsFilled = $remainingKpis->fillGaps();

    expect($remainingKpisWithGapsFilled)->toHaveCount($expectedKpisCount);
})->with($supportedIntervals);

it('can fill gaps between intervals with sibling value', function ($interval) {
    $key = "test:fillGapsWithSibling:{$interval->value}";
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
})->with($supportedIntervals);
