<?php

namespace Finller\Kpi\Support;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Exception;
use Finller\Kpi\Enums\KpiInterval;
use Finller\Kpi\Kpi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @template TKey of array-key
 * @template TModel of Kpi
 *
 * @extends \Illuminate\Database\Eloquent\Collection<TKey, TModel>
 */
class KpiCollection extends Collection
{
    /**
     * Find missing Kpi dates
     */
    public function findGaps(
        KpiInterval $interval,
        ?Carbon $start = null,
        ?Carbon $end = null,
    ): SupportCollection {
        if ($this->count() < 2) {
            return collect();
        }

        $items = $this->sortBy('created_at');
        $start ??= $items->first()?->created_at;
        $end ??= $items->last()?->created_at;

        $dateFormatComparator = $interval->dateFormatComparator();

        $expected = CarbonPeriod::start($start, true)
            ->end($end, true)
            ->interval("1 {$interval->value}")
            ->toArray();

        $actual = $this->pluck('created_at')->toArray();

        $gaps = array_udiff($expected, $actual, function (Carbon $a, Carbon $b) use ($dateFormatComparator): int {
            if ($a->isSameAs($dateFormatComparator, $b)) {
                return 0;
            }
            if ($a->isBefore($b)) {
                return -1;
            }

            return 1;
        });

        return collect($gaps);
    }

    public function getStartDate(): ?Carbon
    {
        return $this->first()?->created_at;
    }

    public function getEndDate(): ?Carbon
    {
        return $this->last()?->created_at;
    }

    public function fillGaps(
        ?Carbon $start = null,
        ?Carbon $end = null,
        ?KpiInterval $interval = null,
        ?array $default = null
    ): static {
        /** @var Kpi */
        $model = config('kpi.kpi_model');

        $items = $this->sortBy('created_at');

        if (! $interval && ($this->count() < 2)) {
            throw new Exception("Interval between items can't be guessed from a single element, provid the interval parameter.");
        }

        if ($items->isEmpty() && ! $default) {
            throw new Exception("The gaps can't be filled when the Kpi collection is empty and no default value is provided.");
        }

        $start = $start ?? $this->getStartDate();
        $end = $end ?? $this->getEndDate();
        $interval = $interval ?? $this->guessInterval();

        if (! $start || ! $end || ! $interval) {
            return $items;
        }

        $dateFormatComparator = $interval->dateFormatComparator();

        if ($start->isAfter($end)) {
            return $items;
        }

        $date = $start->clone();
        $key = 0;

        while (! $date->isSameAs($dateFormatComparator, $end->clone()->add($interval->value, 1))) {

            $item = $items->get($key);
            $previousItem = $items->get($key - 1); // will be used as a placeholder if no default is provided
            $firstItem = $items->first(); // will be used as a placeholder if the first date is missing

            $placeholderValue = $default ?? $previousItem?->attributesToArray() ?? $firstItem->attributesToArray();

            $placeholder = new $model();
            $placeholder->fill($placeholderValue);
            $placeholder->created_at = $date->clone();
            $placeholder->updated_at = $date->clone();

            if (! $item) {
                $items->put($key, $placeholder);
            } elseif (! $item->created_at->isSameAs($dateFormatComparator, $date)) {
                $items->splice(
                    offset: $key,
                    length: 0,
                    replacement: [$placeholder]
                );
            }

            $date->add($interval->value, 1);
            $key += 1;
        }

        return $items;
    }

    public static function getIntervalFromDates(Carbon $before, Carbon $after): ?KpiInterval
    {
        if ($after->isSameDay($before->clone()->addDay())) {
            return KpiInterval::Day;
        }

        if ($after->isSameDay($before->clone()->addWeek())) {
            return KpiInterval::Week;
        }

        if ($after->isSameDay($before->clone()->addMonth())) {
            return KpiInterval::Month;
        }

        if ($after->isSameDay($before->clone()->addYear())) {
            return KpiInterval::Year;
        }

        return null;
    }

    public function guessInterval(): ?KpiInterval
    {
        if ($this->count() < 2) {
            return null;
        }

        /** @var Kpi */
        $firstItem = $this->get(0);
        /** @var Kpi */
        $secondItem = $this->get(1);

        return static::getIntervalFromDates($firstItem->created_at, $secondItem->created_at);
    }

    /**
     * Combine the collection with another one based on keys
     *
     * @param  callable(Kpi, null|Kpi): Kpi  $callback
     */
    public function combineWith(KpiCollection $kpiCollection, callable $callback): static
    {
        /** @var static $collection */
        $collection = $this->map(function (Kpi $kpi, $index) use ($kpiCollection, $callback) {
            return $callback($kpi, $kpiCollection->get($index));
        });

        return $collection;
    }

    public function toRelative(): static
    {
        /** @var static $collection */
        $collection = $this->map(function (Kpi $kpi, $index) {
            /** @var ?Kpi */
            $previousKpi = $this->get($index - 1);

            return $kpi->toDifference($previousKpi);
        });

        return $collection;
    }

    /**
     * @return null|array{number_value: ?float, money_value: ?float, json_value: null, string_value: null}
     */
    public function getVariation()
    {
        $first = $this->first();
        $last = $this->last();

        if (! $last || ! $first) {
            return null;
        }

        return $last->toVariation($first);
    }
}
