<?php

namespace Coolsam\Modules\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isActive(\Coolsam\Modules\Contracts\ModuleDefinition|string $module, string|int|null $tenantId = null)
 * @method static \Illuminate\Support\Collection<int, \Coolsam\Modules\Contracts\ModuleDefinition> active(string|int|null $tenantId = null)
 * @method static void activate(\Coolsam\Modules\Contracts\ModuleDefinition|string $module, string|int|null $tenantId = null)
 * @method static void deactivate(\Coolsam\Modules\Contracts\ModuleDefinition|string $module, string|int|null $tenantId = null)
 *
 * @see \Coolsam\Modules\Contracts\ModuleActivator
 */
class ModuleActivator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Coolsam\Modules\Contracts\ModuleActivator::class;
    }
}
