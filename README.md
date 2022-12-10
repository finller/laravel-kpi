# This is my package laravel-kpi

[![Latest Version on Packagist](https://img.shields.io/packagist/v/finller/laravel-kpi.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-kpi)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/finller/laravel-kpi/run-tests?label=tests)](https://github.com/finller/laravel-kpi/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/finller/laravel-kpi/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/finller/laravel-kpi/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/finller/laravel-kpi.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-kpi)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installation

You can install the package via composer:

```bash
composer require finller/laravel-kpi
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-kpi-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-kpi-config"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
Kpi::create([
    'key' => 'users:count',
    'number_value' => User::count(),
]);
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

- [Quentin Gabriele](https://github.com/QuentinGab)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
