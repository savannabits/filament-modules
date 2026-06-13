<?php

namespace Coolsam\Modules\Dependencies;

use Coolsam\Modules\Contracts\DependencyResolver;
use Coolsam\Modules\Contracts\ModuleActivator;
use Coolsam\Modules\Contracts\ModuleDefinition;
use Coolsam\Modules\Contracts\ModuleRegistry;
use Coolsam\Modules\Exceptions\ModuleIsDependedUponException;
use Coolsam\Modules\Exceptions\UnsatisfiedDependenciesException;
use RuntimeException;

class ModuleDependencyResolver implements DependencyResolver
{
    public function __construct(
        protected ModuleRegistry $registry,
    ) {}

    protected function activator(): ModuleActivator
    {
        return app(ModuleActivator::class);
    }

    public function assertCanActivate(ModuleDefinition | string $module, string | int | null $tenantId = null): void
    {
        $definition = $this->resolveDefinition($module);
        $missing = [];

        foreach ($this->activationOrder($definition) as $dependency) {
            if ($dependency === $definition->name()) {
                continue;
            }

            if (! $this->registry->exists($dependency)) {
                $missing[] = $dependency;
            }
        }

        if ($missing !== []) {
            throw new UnsatisfiedDependenciesException($definition->name(), array_values(array_unique($missing)));
        }
    }

    public function assertCanDeactivate(ModuleDefinition | string $module, string | int | null $tenantId = null): void
    {
        $definition = $this->resolveDefinition($module);
        $dependents = $this->dependents($definition, $tenantId);

        if ($dependents !== []) {
            throw new ModuleIsDependedUponException($definition->name(), $dependents);
        }
    }

    public function activationOrder(ModuleDefinition | string $module): array
    {
        $definition = $this->resolveDefinition($module);
        $order = [];
        $visited = [];

        $this->walkDependencies($definition->name(), $order, $visited);

        if (! in_array($definition->name(), $order, true)) {
            $order[] = $definition->name();
        }

        return $order;
    }

    public function dependents(ModuleDefinition | string $module, string | int | null $tenantId = null): array
    {
        $definition = $this->resolveDefinition($module);
        $dependents = [];

        foreach ($this->registry->all() as $candidate) {
            if (! in_array($definition->name(), $candidate->dependencies(), true)) {
                continue;
            }

            if (! $this->activator()->isActive($candidate, $tenantId)) {
                continue;
            }

            $dependents[] = $candidate->name();
        }

        return array_values(array_unique($dependents));
    }

    /**
     * @param  list<string>  $order
     * @param  array<string, bool>  $visited
     */
    protected function walkDependencies(string $moduleName, array &$order, array &$visited): void
    {
        if (isset($visited[$moduleName])) {
            return;
        }

        $visited[$moduleName] = true;

        $definition = $this->registry->find($moduleName);

        if (! $definition) {
            return;
        }

        foreach ($definition->dependencies() as $dependency) {
            $this->walkDependencies($dependency, $order, $visited);

            if (! in_array($dependency, $order, true)) {
                $order[] = $dependency;
            }
        }
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
}
