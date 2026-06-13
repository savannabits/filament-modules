<?php

namespace Coolsam\Modules\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection<int, \Coolsam\Modules\Contracts\ModuleDefinition> all()
 * @method static \Coolsam\Modules\Contracts\ModuleDefinition|null find(string $name)
 * @method static bool exists(string $name)
 *
 * @see \Coolsam\Modules\Contracts\ModuleRegistry
 */
class ModuleRegistry extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Coolsam\Modules\Contracts\ModuleRegistry::class;
    }
}
