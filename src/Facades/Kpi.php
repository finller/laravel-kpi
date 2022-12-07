<?php

namespace Finller\Kpi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Finller\Kpi\Kpi
 */
class Kpi extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Finller\Kpi\Kpi::class;
    }
}
