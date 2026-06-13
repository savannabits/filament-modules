<?php

namespace Coolsam\Modules\Activation;

use Coolsam\Modules\Contracts\DependencyResolver;
use Coolsam\Modules\Contracts\ModuleActivator;
use Coolsam\Modules\Contracts\ModuleDefinition;
use Coolsam\Modules\Contracts\ModuleRegistry;
use Coolsam\Modules\Contracts\TenantContext;
use Illuminate\Support\Collection;
use Nwidart\Modules\Facades\Module;
use RuntimeException;

class FileModuleActivator implements ModuleActivator
{
    public function __construct(
        protected ModuleRegistry $registry,
        protected DependencyResolver $dependencyResolver,
        protected TenantContext $tenantContext,
    ) {}

    public function isActive(ModuleDefinition | string $module, string | int | null $tenantId = null): bool
    {
        $tenantId = $tenantId ?? $this->tenantContext->resolve();

        if ($tenantId !== null && config('filament-modules.tenancy.enabled', false)) {
            throw new RuntimeException(
                'Per-tenant activation requires the database activator. See V6-ROADMAP.md Milestone 2.'
            );
        }

        $name = $this->resolveName($module);

        return Module::find($name)?->isEnabled() ?? false;
    }

    public function active(string | int | null $tenantId = null): Collection
    {
        return $this->registry->all()
            ->filter(fn (ModuleDefinition $module) => $this->isActive($module, $tenantId))
            ->values();
    }

    public function activate(ModuleDefinition | string $module, string | int | null $tenantId = null): void
    {
        $tenantId = $tenantId ?? $this->tenantContext->resolve();
        $definition = $this->resolveDefinition($module);

        if ($tenantId !== null && config('filament-modules.tenancy.enabled', false)) {
            throw new RuntimeException(
                'Per-tenant activation requires the database activator. See V6-ROADMAP.md Milestone 2.'
            );
        }

        $this->dependencyResolver->assertCanActivate($definition, $tenantId);

        foreach ($this->dependencyResolver->activationOrder($definition) as $moduleName) {
            Module::find($moduleName)?->enable();
        }
    }

    public function deactivate(ModuleDefinition | string $module, string | int | null $tenantId = null): void
    {
        $tenantId = $tenantId ?? $this->tenantContext->resolve();
        $definition = $this->resolveDefinition($module);

        if ($tenantId !== null && config('filament-modules.tenancy.enabled', false)) {
            throw new RuntimeException(
                'Per-tenant activation requires the database activator. See V6-ROADMAP.md Milestone 2.'
            );
        }

        $this->dependencyResolver->assertCanDeactivate($definition, $tenantId);

        Module::find($definition->name())?->disable();
    }

    protected function resolveDefinition(ModuleDefinition | string $module): ModuleDefinition
    {
        if ($module instanceof ModuleDefinition) {
            return $module;
        }

        $definition = $this->registry->find($module);

        if (! $definition) {
            throw new RuntimeException("Module [{$module}] was not found.");
        }

        return $definition;
    }

    protected function resolveName(ModuleDefinition | string $module): string
    {
        return $module instanceof ModuleDefinition
            ? $module->name()
            : $module;
    }
}
