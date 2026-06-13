<?php

namespace Coolsam\Modules\Facades;

use Coolsam\Modules\Modules;
use Illuminate\Support\Facades\Facade;

/**
 * @see Modules
 */
class FilamentModules extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Modules::class;
    }
}
