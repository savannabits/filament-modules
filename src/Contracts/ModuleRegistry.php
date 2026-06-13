<?php

namespace Coolsam\Modules\Contracts;

use Illuminate\Support\Collection;

interface ModuleRegistry
{
    /**
     * @return Collection<int, ModuleDefinition>
     */
    public function all(): Collection;

    public function find(string $name): ?ModuleDefinition;

    public function exists(string $name): bool;
}
