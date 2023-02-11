<?php

namespace Finller\Kpi;

use Carbon\Carbon;
use Finller\Kpi\Support\KpiCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property string $key
 * @property ?string $string_value
 * @property ?float $number_value
 * @property ?array $json_value
 * @property ?int $money_value
 * @property ?string $money_currency
 * @property ?ArrayObject $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Kpi extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'string_value',
        'number_value',
        'json_value',
        'money_value',
        'money_currency',
        'metadata',
    ];

    protected $casts = [
        'number_value' => 'float',
        'json_value' => 'array',
        'metadata' => AsArrayObject::class,
    ];

    public function scopePerDay(Builder $query)
    {
        return $query->perInterval(DB::raw("DAY(created_at), MONTH(created_at), YEAR(created_at)")); // @phpstan-ignore-line
    }

    public function scopePerWeek(Builder $query)
    {
        return $query->perInterval(DB::raw("WEEK(created_at), MONTH(created_at), YEAR(created_at)")); // @phpstan-ignore-line
    }

    public function scopePerMonth(Builder $query)
    {
        return $query->perInterval(DB::raw("MONTH(created_at), YEAR(created_at)")); // @phpstan-ignore-line
    }

    public function scopePerYear(Builder $query)
    {
        return $query->perInterval(DB::raw("YEAR(created_at)")); // @phpstan-ignore-line
    }

    protected function scopePerInterval(Builder $query, \Illuminate\Database\Query\Expression $groupBy)
    {
        // find what key is searched for
        $key = collect($query->getQuery()->wheres)
            ->where('column', 'key')
            ->pluck('value')
            ->first();

        //only get most recent kpi for each day
        return $query->whereIn('id', function ($q) use ($key, $groupBy) {
            $q
                ->from($this->table)
                ->where('key', $key)
                ->select(DB::raw('max(id) as id'))
                ->groupBy($groupBy);
        });
    }

    public function scopeBetween(Builder $query, ?Carbon $start = null, ?Carbon $end = null)
    {
        return $query
            ->when($start, fn ($q) => $q->whereDate('created_at', ">=", $start))
            ->when($start, fn ($q) => $q->whereDate('created_at', "<", $end));
    }

    public function newCollection(array $models = []): KpiCollection
    {
        return new KpiCollection($models);
    }
}
