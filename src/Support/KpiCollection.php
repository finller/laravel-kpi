<?php

namespace Finller\Kpi\Support;

use Carbon\Carbon;
use Exception;
use Finller\Kpi\Kpi;
use Illuminate\Database\Eloquent\Collection;

class KpiCollection extends Collection
{
    public function fillGaps(?Carbon $start = null, ?Carbon $end = null, ?string $interval = null, ?array $default = null): static
    {
        $collection = new static($this->sortBy('created_at')->all());  // @phpstan-ignore-line

        if (! $interval && ($this->count() < 2)) {
            throw new Exception("interval between items can't be guessed from a single element, provid the interval parameter.");
        }

        /** @var ?Carbon $start */
        $start = $start ?? $collection->first()?->created_at;
        /** @var ?Carbon $end */
        $end = $end ?? $collection->last()?->created_at;

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
                $placeholder = new Kpi(
                    $default ??
                        $collection->get($indexItem - 1)?->toArray() ??
                        $item?->toArray() ??
                        $collection->last()->toArray()
                );
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

    public static function getFormatDateComparator(string $interval)
    {
        return match ($interval) {
            'day' => 'Y-m-d',
            'week' => 'Y-W',
            'month' => 'Y-m',
            'year' => 'Y',
        };
    }

    public static function getIntervalFromDates(Carbon $before, Carbon $after): ?string
    {
        if ($after->isNextDay($before)) {
            return 'day';
        }

        if ($after->isNextWeek($before)) {
            return 'week';
        }

        if ($after->isNextMonth($before)) {
            return 'month';
        }

        if ($after->isNextYear($before)) {
            return 'year';
        }

        return null;
    }

    public function guessInterval(): ?string
    {
        if ($this->count() < 2) {
            return null;
        }

        return static::getIntervalFromDates($this->get(0)->created_at, $this->get(1)->created_at);
    }
}
