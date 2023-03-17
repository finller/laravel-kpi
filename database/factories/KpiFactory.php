<?php

namespace Finller\Kpi\Database\Factories;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Finller\Kpi\Enums\KpiInterval;
use Finller\Kpi\Kpi;
use Illuminate\Database\Eloquent\Factories\Factory;

class KpiFactory extends Factory
{
    protected $model = Kpi::class;

    public function definition()
    {
        return [
            'key' => fake()->randomElement(['users', 'messages', 'pages']) . ':' . fake()->randomElement(['count', 'max', 'min']),
        ];
    }

    /**
     * @return static
     */
    public function number()
    {
        return $this->state(function (array $attributes) {
            return [
                'number_value' => fake()->randomFloat(0, 0, 1000),
            ];
        });
    }

    /**
     * @return static
     */
    public function string()
    {
        return $this->state(function (array $attributes) {
            return [
                'number_value' => fake()->word(),
            ];
        });
    }

    /**
     * @return static
     */
    public function money()
    {
        return $this->state(function (array $attributes) {
            return [
                'money_value' => (int) fake()->randomFloat(0, 0, 1000000),
                'money_currency' => fake()->currencyCode(),
            ];
        });
    }

    /**
     * @return static
     */
    public function json()
    {
        return $this->state(function (array $attributes) {
            return [
                'json_value' => fake()->words(),
            ];
        });
    }

    public function between(Carbon $start, Carbon $end, KpiInterval $interval = KpiInterval::Day, ?array $properties = [])
    {
        $period = CarbonPeriod::between($start, $end)
            ->interval("1 {$interval->value}")
            ->toArray();

        return $this
            ->count(count($period))
            ->sequence(
                ...array_map(
                    fn (Carbon $date) => [...$properties, 'created_at' => $date->clone(), 'updated_at' => $date->clone()],
                    $period
                )
            );
    }
}
