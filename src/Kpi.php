<?php

namespace Finller\Kpi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public function scopeByDay(Builder $query)
    {
        // find what key is searched for
        $key = collect($query->getQuery()->wheres)
            ->where('column', 'key')
            ->pluck('value')
            ->first();

        //only get most recent kpi for each day
        return $query->whereIn('id', function ($q) use ($key) {
            $q
                ->from($this->table)
                ->where('key', $key)
                ->select(DB::raw('max(id) as id'))
                ->groupBy(DB::raw('DATE(created_at)'));
        });
    }
}
