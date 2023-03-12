<?php

namespace Finller\Kpi\Support;

use Carbon\Carbon;
use Exception;
use Finller\Kpi\Kpi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class KpiCollection extends Collection
{
    public function fillGaps(?Carbon $start = null, ?Carbon $end = null, ?string $interval = null, ?array $default = null): static
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
        $dateFormatComparator = static::getFormatDateComparator($interval);

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
            $date->add($interval, 1);
        }

        return $collection;
    }

    public static function getFormatDateComparator(string $interval): ?string
    {
        return match ($interval) {
            'day' => 'Y-m-d',
            'week' => 'Y-W',
            'month' => 'Y-m',
            'year' => 'Y',
            default => null
        };
    }

    public static function getIntervalFromDates(Carbon $before, Carbon $after): ?string
    {
        if ($after->isSameDay($before->clone()->addDay())) {
            return 'day';
        }

        if ($after->isSameDay($before->clone()->addWeek())) {
            return 'week';
        }

        if ($after->isSameDay($before->clone()->addMonth())) {
            return 'month';
        }

        if ($after->isSameDay($before->clone()->addYear())) {
            return 'year';
        }

        return null;
    }

    public function guessInterval(): ?string
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
}
