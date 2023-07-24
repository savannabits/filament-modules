<?php

namespace Coolsam\FilamentModules\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Coolsam\FilamentModules\Modules
 */
class Modules extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Coolsam\FilamentModules\Modules::class;
    }
}
