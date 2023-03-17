<?php

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

    expect($relativeCollection)->toHaveCount($collection->count() - 1);
    expect($relativeCollection->get(0)?->number_value)->toBe(10.0);
    expect($relativeCollection->get(1)?->number_value)->toBe(90.0);
    expect($relativeCollection->get(2)?->number_value)->toBe(50.0);
})->with($supportedIntervals);
