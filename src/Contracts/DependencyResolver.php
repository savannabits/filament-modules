<?php

namespace Coolsam\Modules\Contracts;

interface DependencyResolver
{
    public function assertCanActivate(ModuleDefinition | string $module, string | int | null $tenantId = null): void;

    public function assertCanDeactivate(ModuleDefinition | string $module, string | int | null $tenantId = null): void;

    /**
     * @return list<string>
     */
    public function activationOrder(ModuleDefinition | string $module): array;

    /**
     * @return list<string>
     */
    public function dependents(ModuleDefinition | string $module, string | int | null $tenantId = null): array;
}
