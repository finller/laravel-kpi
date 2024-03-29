<?php

use Carbon\Carbon;
use Finller\Kpi\Enums\KpiInterval;
use Finller\Kpi\Kpi;
use Finller\Kpi\Support\KpiCollection;

$supportedIntervals = KpiInterval::cases();

it('can guess interval between items', function (KpiInterval $interval) {
    $collection = new KpiCollection(
        Kpi::factory(2)->sequence(
            ['created_at' => now()],
            ['created_at' => now()->add($interval->value, 1)],
        )->make()
    );

    expect($collection->guessInterval())->toBe($interval);
})->with($supportedIntervals);

it('can find gaps between items', function (KpiInterval $interval) {
    $collection = new KpiCollection(
        Kpi::factory(5)->sequence(
            ['created_at' => now()],
            ['created_at' => now()->add($interval->value, 1)],
            // missing kpi 2
            ['created_at' => now()->add($interval->value, 3)],
            ['created_at' => now()->add($interval->value, 4)],
            // missing kpi 5
            // missing kpi 6
            ['created_at' => now()->add($interval->value, 7)],
        )->make()
    );
    expect($collection->count())->toBe(5);

    $gaps = $collection->findGaps(interval: $interval);

    expect($gaps->count())->toBe(3);

    $expectedGaps = collect([now()->add($interval->value, 2), now()->add($interval->value, 5), now()->add($interval->value, 6)])
        ->map(fn (Carbon $date) => $date->format($interval->dateFormatComparator()))
        ->values()
        ->toArray();

    $formattedGaps = $gaps->map(fn (Carbon $date) => $date->format($interval->dateFormatComparator()))->values()->toArray();

    expect($formattedGaps)->toMatchArray($expectedGaps);
})->with($supportedIntervals);

it('can fill gaps between items by guessing state', function (KpiInterval $interval) {
    $collectionWithGaps = new KpiCollection(
        Kpi::factory(5)->sequence(
            ['created_at' => now()],
            ['created_at' => now()->add($interval->value, 1)],
            // missing kpi 2
            ['created_at' => now()->add($interval->value, 3)],
            ['created_at' => now()->add($interval->value, 4)],
            // missing kpi 5
            // missing kpi 6
            ['created_at' => now()->add($interval->value, 7)],
        )->number()->make(['key' => 'users:count'])
    );
    expect($collectionWithGaps->count())->toBe(5);

    $collectionWithoutGaps = $collectionWithGaps->fillGaps();

    expect($collectionWithoutGaps->count())->toBe(8);

    expect($collectionWithoutGaps->get(2)->number_value)->toBe($collectionWithoutGaps->get(1)->number_value);
    expect($collectionWithoutGaps->get(5)->number_value)->toBe($collectionWithoutGaps->get(4)->number_value);
    expect($collectionWithoutGaps->get(6)->number_value)->toBe($collectionWithoutGaps->get(4)->number_value);

})->with($supportedIntervals);

it('can fill gaps between items with explicite state', function (KpiInterval $interval) {
    $collectionWithGaps = new KpiCollection(
        Kpi::factory(4)->sequence(
            // missing kpi 0
            ['created_at' => now()->add($interval->value, 1)],
            // missing kpi 2
            ['created_at' => now()->add($interval->value, 3)],
            ['created_at' => now()->add($interval->value, 4)],
            // missing kpi 5
            // missing kpi 6
            ['created_at' => now()->add($interval->value, 7)],
            // missing kpi 8
        )->number()->make(['key' => 'users:count'])
    );
    expect($collectionWithGaps->count())->toBe(4);

    $collectionWithoutGaps = $collectionWithGaps->fillGaps(
        start: now()->add($interval->value, 0),
        end: now()->add($interval->value, 8),
        interval: $interval
    );

    expect($collectionWithoutGaps->count())->toBe(9);

    expect($collectionWithoutGaps->get(0)->number_value)->toBe($collectionWithoutGaps->get(1)->number_value);
    expect($collectionWithoutGaps->get(2)->number_value)->toBe($collectionWithoutGaps->get(1)->number_value);
    expect($collectionWithoutGaps->get(6)->number_value)->toBe($collectionWithoutGaps->get(4)->number_value);
    expect($collectionWithoutGaps->get(8)->number_value)->toBe($collectionWithoutGaps->get(7)->number_value);

})->with($supportedIntervals);

it('can fill gaps between with an empty collection by including start and end date', function () {

    $interval = KpiInterval::Month;

    $collectionWithGaps = new KpiCollection();

    expect($collectionWithGaps->count())->toBe(0);

    $collectionWithoutGaps = $collectionWithGaps->fillGaps(
        start: Carbon::parse('2022-12-10 15:39:32'),
        end: Carbon::parse('2023-12-10 15:39:32'),
        interval: $interval,
        default: [
            'number_value' => 0,
        ]
    );

    expect($collectionWithoutGaps->count())->toBe(13);
});

it('can combine two KpiCollections', function (KpiInterval $interval) {
    $now = now();

    $collection1 = new KpiCollection(
        Kpi::factory(2)->sequence(
            [
                'created_at' => $now->clone(),
                'number_value' => 1.0,
            ],
            [
                'created_at' => $now->clone()->add($interval->value, 1),
                'number_value' => 2.0,
            ],
        )->make()
    );

    $collection2 = new KpiCollection(
        Kpi::factory(2)->sequence(
            [
                'created_at' => $now->clone(),
                'number_value' => 0.0,
            ],
            [
                'created_at' => $now->clone(),
                'number_value' => 10.0,
            ],
            [
                'created_at' => $now->clone()->add($interval->value, 1),
                'number_value' => 20.0,
            ],
        )->make()
    );

    $combinedCollection = $collection1
        ->combineWith($collection2, function (Kpi $kpi1, ?Kpi $kpi2) {
            if (! $kpi2) {
                return $kpi1;
            }

            return new Kpi([
                ...$kpi1->toArray(),
                'number_value' => $kpi1->number_value + floatval($kpi2->number_value),
            ]);
        });

    expect($combinedCollection->get(0)?->number_value)->toBe(1.0);
    expect($combinedCollection->get(1)?->number_value)->toBe(12.0);
})->with($supportedIntervals);

it('can convert to a relative collection', function (KpiInterval $interval) {
    $collection = new KpiCollection(
        Kpi::factory(4)->sequence(
            [
                'created_at' => now(),
                'number_value' => 0,
            ],
            [
                'created_at' => now()->add($interval->value, 1),
                'number_value' => 10,
            ],
            [
                'created_at' => now()->add($interval->value, 2),
                'number_value' => 100,
            ],
            [
                'created_at' => now()->add($interval->value, 3),
                'number_value' => 150,
            ],
        )->make()
    );

    $relativeCollection = $collection->toRelative();

    expect($relativeCollection->get(0)?->number_value)->toBe(null);
    expect($relativeCollection->get(1)?->number_value)->toBe(10.0);
    expect($relativeCollection->get(2)?->number_value)->toBe(90.0);
    expect($relativeCollection->get(3)?->number_value)->toBe(50.0);
})->with($supportedIntervals);
