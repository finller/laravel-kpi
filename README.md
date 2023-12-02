# Store, analyse and retrieve KPI over time in your Laravel App

[![Latest Version on Packagist](https://img.shields.io/packagist/v/finller/laravel-kpi.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-kpi)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/finller/laravel-kpi/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/finller/laravel-kpi/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/finller/laravel-kpi/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/finller/laravel-kpi/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/finller/laravel-kpi.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-kpi)

This package provides a way to store kpis from your app in your database and then retreive them easily in different ways. It is espacially usefull to tracks things related to your models like:

-   the number of users
-   the number of subscribed users
-   the total revenue
-   ...

It's a perfect tool for building dashboard ans display stats/charts.

## Installation

You can install the package via composer:

```bash
composer require finller/laravel-kpi
```

You have to publish and run the migrations with:

```bash
php artisan vendor:publish --tag="kpi-migrations"
php artisan migrate
```

## Usage

This package is **not a query builder**, it's based on a kpi table where you will store all your kpis.
With this approach, your kpis from the past (like the number of users you had a year ago) will not be altered if you permanently delete a model.
Rretreiving kpis will aslo be much more efficient when asking for computed values that often require join like "users who have purchased last week" for example.

### Step 1: Store kpis in you database

As said above, you will have to store the kpis you need in the database. Kpis are grouped by `keys` and support different kind of values:

-   number (float) under `number_value` column
-   string under `string_value` column
-   json or array under `json_value` column
-   money under `money_value` and `money_currency` column

In most cases, you would store numbers, like the number of users for example. You are free to choose the key but you could do it like that:

```php
Kpi::create([
    'key' => 'users:count',
    'number_value' => User::count(),
]);
```

#### Define KPIs for each model

Generally kpis are related to models, that's why we provid a trait `HasKpi` with a standardized way to name your kpi key `{namespace}:{key}`. For the User model, it would store your key in the `users` namespace like `users:{key}`.

By default the trait only define 1 KPI: `Model::count()`

You can define your own KPIs freely with the method `registerKpis`. Here is an example:

```php
namespace App\Models;

use Finller\Kpi\HasKpi;

class User extends Model
{
    use HasKpi;

    /*
     * The date represent the date of the KPI
     */
    public static function registerKpis(Carbon $date = null): Collection
    {
        /** @var Builder $query */
        $query = static::query()->when($date, fn (Builder $q) => $q->whereDate('created_at', '<=', $date->clone()));

        return collect()
            // the count Kpi is always snapshot, you don't need to register it
            // ->push(fn () => new Kpi([
            //     'key' => static::getKpiNamespace() . ':count',
            //     'number_value' => $query->clone()->count(),
            //     'created_at' => $date->clone(),
            // ]))
            ->push(fn () => new Kpi([
                'key' => static::getKpiNamespace() . ':active:count',
                'number_value' => $query->clone()->active()->count(),
                'created_at' => $date->clone(),
            ]))
            ->push(fn () => new Kpi([
                'key' => static::getKpiNamespace() . ':subscribed:count',
                'number_value' => $query->clone()->subscribed()->count(),
                'created_at' => $date->clone(),
            ]));
    }
}
```

Notice that, the method accept a `$date` parameter. This allow you to take KPIs snapshot "in the past".
This is usefull for already existing project, or simply when you add a new KPI to the list.

Then, you have to save those Kpis in the database with the `snapshotKpis` method.

A standard way to save your kpi values would be in a command that runs every day.

Here is an example:

```php
namespace App\Console\Commands;

use App\Models\User;

class SnapshotKpisCommand extends Command
{
    protected $signature = 'kpis:snapshot';
    protected $description = 'Snapshot KPI';

    public function handle()
    {
        User::snapshotKpis();
    }
}
```

Then add it to `Kernel`

```php
namespace App\Console;

use App\Console\Commands\SnapshotKpiCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {
        $schedule->command(SnapshotKpiCommand::class)->dailyAt('00:00');
    }
}
```

You are free to store as much kpis as needed, even multiple times in a day, so you can get more recent data.

### Step 2: Retreive your kpis

You can retreive kpis by using usefull scopes and the native eloquent Builder methods.

For example, if I want to query kpis under `users:count`, you could use:

```php
// With Kpi model
use Finller\Kpi\Kpi;
Kpi::where('key', "users:count")->get();

// With HasKpi trait
use App\Models\User;
User::kpi('count')->get();

// With KpiBuilder
use Finller\Kpi\KpiBuilder;
KpiBuilder::query("users:count")->get();
KpiBuilder::query(Kpi::query()->where("key", "users:count"))->get();
```

#### Query by date

In most cases, you would like to have thoses kpis grouped by date (day, month, year, ...).

For example, to get the number of users grouped by day between 2 dates (usefull to draw a chart), you could do:

```php
User::kpi('count')
    ->between(start: now(), end: now()->subDays(7))
    ->perDay()
    ->get();
```

As we are grouping by date/period, you could have more than 1 snapshot of the same key for a date/period. In this situation, this package will give you only the most recent snaphot of each date/period.

In the previous example, I would get the most recent count of users for each day. This is also true for other kind of supported intervals.

#### Supported intervals:

The supported intervals are: perDay, perWeek, perMonth and perYear.

```php
Kpi::query()->perDay()->get();
Kpi::query()->perWeek()->get();
Kpi::query()->perMonth()->get();
Kpi::query()->perYear()->get();
```

#### Fill gaps between dates

In some cases, you could have missed a snapshot. Let's say that your snapshot kpi command failed or your server was down.

To fill the gaps let by missing values, you can use the `fillGaps` method available on `KpiBuilder` or `KpiCollection`.
By default the placeholders will be a copy of their previous kpi.

For convenience the `KpiBuilder` is the best option as it will give you better typed values and shares parameters between `fillGaps` and `between`.

```php
use Finller\Kpi\Enums\KpiInterval;

Kpi::query()
    ->where('key', 'users:blocked:count')
    ->between(now()->subWeek(), now())
    ->perDay()
    ->get()
    ->fillGaps( // optional parameters
        start: now()->subWeek(),
        end: now(),
        interval: KpiInterval::Day,
        default: ['number_value' => 0]
    );

KpiBuilder::query('users:blocked:count')
    ->perDay()
    ->between(now()->subWeek(), now())
    ->fillGaps()
    ->get();

Kpi::query()
    ->where('key', 'users:blocked:count')
    ->between(now()->subWeek(), now())
    ->perDay()
    ->get()
    ->fillGaps(); // if you do not specify anything when using KpiCollection, the start, end and the interval values will be guessed from your dataset
```

#### Find gaps in Kpis

You can also find gaps in a KpiCollection using `findGaps` method.
It can be usefull to snapshot these missing kpi later with something like:

```php
$gaps = Model::kpi('count')
    ->perDay()
    ->between(now()->subMonth())
    ->get()
    ->findGaps(interval: KpiInterval::Day, start: now()->subMonth(), end: now());

$gaps->each(function(Carbon $date){
    Model::snapshotKpis($date);
});
```

#### Fill KPIs on a period for existing projects or when adding new KPIs

For existing project or when ading a new KPI, you may want to fill your KPIs with data from the past.

The `HasKpi` trait allow you to do it like so:

```php
Model::backfillKpis(
    start: now()->subYear(),
    end: now(),
    interval: KpiInterval::Day
);
```

#### Get relative values

Some Kpis are only relevant when taken relatively to others or to a period.

For example, the number of new registered users by month can be obtained without registering a specific KPIs,
but by taking the relative values of the default `User::count()` KPI.

You can easily get your Kpis in a relative format by using different methods:

To go from:
| Jan | Feb | Mar |
|-----|-----|-----|
| 10 | 100 | 500 |

To:

| Jan | Feb | Mar |
| --- | --- | --- |
| 0   | 90  | 400 |

```php
KpiBuilder::query('users:count')
    ->relative()
    ->perMonth()
    ->between(now()->subYear(), now())
    ->get();

Kpi::query()
    ->where('key', 'users:blocked:count')
    ->between(now()->subYear(), now())
    ->perMonth()
    ->get()
    ->toRelative(); // when using KpiCollection
```

#### Combine multiple Kpis together

When a KPI is a direct result of a combination of 2 other KPIs, it might not be worth to save it in the database.
You can combine KPIs together to create a new one simply by using the `combineWith` method from the `KpiCollection`.

Here is an example:

```php
$usersCount = User::kpi('count')->perDay()->get();

$subscribedUsersCount = User::kpi('subscribed:count')->perDay()->get();

$usersCount->combineWith(
    $subscribedUsersCount,
    fn(Kpi $userCount, Kpi $subscribedUsersCount) => new Kpi([
        ...$userCount->toArray,
        'number_value' => $subscribedUsersCount->number_value / $userCount->number_value
    ])
);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Quentin Gabriele](https://github.com/QuentinGab)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
