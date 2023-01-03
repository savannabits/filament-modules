<?php

namespace Savannabits\FilamentModules\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Savannabits\FilamentModules\FilamentModules
 */
class FilamentMultiGuard extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Savannabits\FilamentModules\FilamentModules::class;
    }
}
