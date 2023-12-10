<?php

namespace Finller\Kpi;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Finller\Kpi\Enums\KpiInterval;
use Finller\Kpi\Support\KpiCollection;
use Illuminate\Database\Eloquent\Builder;

class KpiBuilder
{
    public ?KpiInterval $interval = null;

    public ?CarbonInterface $start = null;

    public ?CarbonInterface $end = null;

    public bool $fillGaps = false;

    /**
     * If true, value will not be not be cumulated
     */
    public bool $relative = false;

    public ?array $defaultGapValue = null;

    public function __construct(public Builder $builder)
    {
        //
    }

    public static function query(string|Builder|null $builder = null): self
    {
        $model = config('kpi.kpi_model');

        if ($builder instanceof Builder) {
            return new self($builder);
        }

        if (is_string($builder)) {
            return new self($model::query()->where('key', $builder));
        }

        return new self($model::query());
    }

    public function relative($value = true): static
    {
        $this->relative = $value;

        return $this;
    }

    public function between(?CarbonInterface $start = null, ?CarbonInterface $end = null): static
    {
        $this->start = $start;
        $this->end = $end;

        return $this;
    }

    /**
     * Set start and end from a CarbonPeriod
     */
    public function period(CarbonPeriod $period): static
    {
        $this->start = $period->getStartDate();
        $this->end = $period->getEndDate();

        return $this;
    }

    public function after(Carbon $date): static
    {
        $this->start = $date;

        return $this;
    }

    public function before(Carbon $date): static
    {
        $this->end = $date;

        return $this;
    }

    public function perInterval(KpiInterval $interval): static
    {
        $this->interval = $interval;

        return $this;
    }

    public function perDay(): static
    {
        return $this->perInterval(KpiInterval::Day);
    }

    public function perWeek(): static
    {
        return $this->perInterval(KpiInterval::Week);
    }

    public function perMonth(): static
    {
        return $this->perInterval(KpiInterval::Month);
    }

    public function perYear(): static
    {
        return $this->perInterval(KpiInterval::Year);
    }

    public function getQuery(): Builder
    {
        $start = $this->relative && $this->interval
            // When querying for relative values, we must get one additionnal interval in the past to compute difference
            ? $this->start?->clone()->sub($this->interval->value, 1)
            : $this->start;

        return $this->builder
            ->when($start, fn ($q) => $q->where('kpis.created_at', '>=', $start))
            ->when($this->end, fn ($q) => $q->where('kpis.created_at', '<=', $this->end))
            ->when($this->interval, fn ($q) => $q->perInterval($this->interval));
    }

    public function fillGaps(?array $defaultGapValue = null): static
    {
        $this->fillGaps = true;
        $this->defaultGapValue = $defaultGapValue;

        return $this;
    }

    public function latest(): static
    {
        $this->builder->latest('created_at');

        return $this;
    }

    public function oldest(): static
    {
        return $this->latest();
    }

    public function count(): int
    {
        return $this->getQuery()->count();
    }

    public function get()
    {
        /** @var KpiCollection */
        $kpis = $this->getQuery()->get();

        if ($this->relative && $this->interval) {
            // When querying relative values we have included one additional interval for computation purpose only
            $kpis = $kpis
                ->fillGaps(
                    start: $this->start,
                    end: $this->end,
                    interval: $this->interval,
                    default: $this->defaultGapValue
                )
                ->toRelative()
                ->skip(1)
                ->values();
        }

        if ($this->fillGaps) {
            $kpis = $kpis->fillGaps(
                start: $this->start,
                end: $this->end,
                interval: $this->interval,
                default: $this->defaultGapValue
            );
        }

        return $kpis;
    }
}
