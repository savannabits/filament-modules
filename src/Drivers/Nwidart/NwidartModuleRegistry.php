<?php

namespace Coolsam\Modules\Drivers\Nwidart;

use Coolsam\Modules\Contracts\ModuleDefinition;
use Coolsam\Modules\Contracts\ModuleRegistry;
use Illuminate\Support\Collection;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Module as NwidartModule;

class NwidartModuleRegistry implements ModuleRegistry
{
    /**
     * @return Collection<int, ModuleDefinition>
     */
    public function all(): Collection
    {
        $definitions = [];

        foreach (Module::all() as $module) {
            $definitions[] = $this->makeDefinition($module);
        }

        return new Collection($definitions);
    }

    public function find(string $name): ?ModuleDefinition
    {
        $module = Module::find($name);

        if (! $module) {
            return null;
        }

        return $this->makeDefinition($module);
    }

    public function exists(string $name): bool
    {
        return Module::find($name) !== null;
    }

    protected function makeDefinition(NwidartModule $module): ModuleDefinition
    {
        return new NwidartModuleDefinition($module);
    }
}
