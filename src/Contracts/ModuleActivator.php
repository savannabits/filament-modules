<?php

namespace Coolsam\Modules\Contracts;

use Illuminate\Support\Collection;

interface ModuleActivator
{
    public function isActive(ModuleDefinition | string $module, string | int | null $tenantId = null): bool;

    /**
     * @return Collection<int, ModuleDefinition>
     */
    public function active(string | int | null $tenantId = null): Collection;

    public function activate(ModuleDefinition | string $module, string | int | null $tenantId = null): void;

    public function deactivate(ModuleDefinition | string $module, string | int | null $tenantId = null): void;
}
