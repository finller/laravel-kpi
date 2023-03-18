<?php

namespace Finller\Kpi\Support;

use Carbon\Carbon;
use Exception;
use Finller\Kpi\Enums\KpiInterval;
use Finller\Kpi\Kpi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class KpiCollection extends Collection
{
    public function fillGaps(?Carbon $start = null, ?Carbon $end = null, ?KpiInterval $interval = null, ?array $default = null): static
    {
        $model = config('kpi.kpi_model');

        $collection = new static($this->sortBy('created_at')->all());  // @phpstan-ignore-line

        if (! $interval && ($this->count() < 2)) {
            throw new Exception("interval between items can't be guessed from a single element, provid the interval parameter.");
        }

        /** @var ?Kpi */
        $firstItem = $collection->first();
        /** @var ?Kpi */
        $lastItem = $collection->last();
        /** @var ?Carbon */
        $start = $start ?? $firstItem?->created_at;
        /** @var ?Carbon */
        $end = $end ?? $lastItem?->created_at;

        $interval = $interval ?? $this->guessInterval();

        if (! $start || ! $end || ! $interval) {
            return $collection;
        }

        $date = $start->clone();
        $indexItem = 0;
        $dateFormatComparator = $interval->dateFormatComparator();

        while ($date->lessThanOrEqualTo($end)) {
            /** @var ?Kpi $item */
            $item = $collection->get($indexItem);

            if (! $item?->created_at->isSameAs($dateFormatComparator, $date)) {
                $placeholderItem = $collection->get($indexItem - 1) ?? $item ?? $collection->last();

                $placeholder = new $model();
                $placeholder->fill(Arr::only($default ?? $placeholderItem?->attributesToArray() ?? [], $placeholder->getFillable()));
                $placeholder->created_at = $date->clone();
                $placeholder->updated_at = $date->clone();

                $collection->splice(
                    $indexItem,
                    0,
                    [$placeholder]
                );
            }

            $indexItem++;
            $date->add($interval->value, 1);
        }

        return $collection;
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
    public function combineWith(KpiCollection $kpiCollection, callable $callback): self
    {
        return new static($this->map(function (Kpi $kpi, $index) use ($kpiCollection, $callback) {
            return $callback($kpi, $kpiCollection->get($index));
        }));
    }

    public function toRelative()
    {
        return new static(
            $this->map(function (Kpi $kpi, $index) {
                /** @var ?Kpi */
                $previousKpi = $this->get($index - 1);

                return new Kpi([
                    'created_at' => $kpi->created_at,
                    'number_value' => $this->toRelativeNumberValue($kpi->number_value, $previousKpi?->number_value),
                    'money_value' => $this->toRelativeMoneyValue($kpi->money_value, $previousKpi?->money_value),
                    'string_value' => $this->toRelativeStringValue($kpi->string_value, $previousKpi?->string_value),
                ]);
            })->values() // The very first value can not be converted to a relative value
        );
    }

    protected function toRelativeNumberValue(null|float|int|string $current, null|float|int|string $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return floatval($current) - floatval($previous);
    }

    protected function toRelativeMoneyValue($current, $previous)
    {
        if (! is_numeric($current) || ! is_numeric($previous)) {
            return null;
        }

        return $this->toRelativeNumberValue($current, $previous);
    }

    protected function toRelativeStringValue(?string $current, ?string $previous): ?string
    {
        return $current;
    }
}
