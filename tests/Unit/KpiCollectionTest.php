<?php

use Carbon\CarbonPeriod;
use Finller\Kpi\Kpi;
use Finller\Kpi\Support\KpiCollection;

$supportedIntervals = ['day', 'week', 'month', 'year'];

it('can guess interval between items', function ($interval) {
    $collection = new KpiCollection(
        Kpi::factory(2)->sequence(
            ['created_at' => now()],
            ['created_at' => now()->add($interval, 1)],
        )->make()
    );

    expect($collection->guessInterval())->toBe($interval);
})->with($supportedIntervals);
