<?php

namespace Finller\Kpi;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Finller\Kpi\Commands\KpiCommand;

class KpiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-kpi')
            ->hasConfigFile()
            // ->hasViews()
            ->hasMigration('create_kpi_table');
            // ->hasCommand(KpiCommand::class);
    }
}
