<?php

namespace Coolsam\Modules\Drivers\Nwidart;

use Coolsam\Modules\Contracts\ModuleDefinition;
use Coolsam\Modules\Contracts\ModuleRegistry;
use Illuminate\Support\Collection;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Module as NwidartModule;

class NwidartModuleRegistry implements ModuleRegistry
{
    public function all(): Collection
    {
        return collect(Module::all())
            ->map(fn (NwidartModule $module) => new NwidartModuleDefinition($module))
            ->values();
    }

    public function find(string $name): ?ModuleDefinition
    {
        $module = Module::find($name);

        if (! $module) {
            return null;
        }

        return new NwidartModuleDefinition($module);
    }

    public function exists(string $name): bool
    {
        return Module::find($name) !== null;
    }
}
