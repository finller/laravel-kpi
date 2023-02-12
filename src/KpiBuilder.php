<?php

namespace Finller\Kpi;

use Carbon\Carbon;
use Finller\Kpi\Support\KpiCollection;
use Illuminate\Database\Eloquent\Builder;

class KpiBuilder
{
    public ?string $interval = null;

    public ?Carbon $start = null;

    public ?Carbon $end = null;

    public bool $fillGaps = false;

    public ?array $defaultGapValue = null;

    public function __construct(public Builder $builder)
    {
        //
    }

    public static function query(null|string|Builder $builder = null): self
    {
        if ($builder instanceof Builder) {
            return new self($builder);
        }

        if (is_string($builder)) {
            new self(Kpi::query()->where('key', $builder));
        }

        return new self(Kpi::query());
    }

    public function between(?Carbon $start = null, ?Carbon $end = null): static
    {
        $this->start = $start;
        $this->end = $end;

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

    public function perInterval(string $interval): static
    {
        $this->interval = $interval;

        return $this;
    }

    public function perDay(): static
    {
        return $this->perInterval('day');
    }

    public function perWeek(): static
    {
        return $this->perInterval('day');
    }

    public function perMonth(): static
    {
        return $this->perInterval('day');
    }

    public function perYear(): static
    {
        return $this->perInterval('day');
    }

    public function getQuery(): Builder
    {
        return $this->builder
            ->when($this->interval, fn ($q) => $q->perInterval($this->interval)) // @phpstan-ignore-line
            ->when($this->start, fn ($q) => $q->where('kpis.created_at', '>=', $this->start))
            ->when($this->end, fn ($q) => $q->where('kpis.created_at', '<=', $this->end));
    }

    public function fillGaps(?array $defaultGapValue = null)
    {
        $this->fillGaps = true;
        $this->defaultGapValue = $defaultGapValue;

        return $this;
    }

    public function get(): KpiCollection
    {
        /** @var KpiCollection */
        $kpis = $this->getQuery()->get();

        return $this->fillGaps ?
            $kpis->fillGaps(
                start: $this->start,
                end: $this->end,
                interval: $this->interval,
                default: $this->defaultGapValue
            ) :
            $kpis;
    }
}
