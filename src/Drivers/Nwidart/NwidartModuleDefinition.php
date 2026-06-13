<?php

namespace Coolsam\Modules\Drivers\Nwidart;

use Coolsam\Modules\Contracts\ModuleDefinition;
use Nwidart\Modules\Module;

class NwidartModuleDefinition implements ModuleDefinition
{
    public function __construct(
        protected Module $module,
    ) {}

    public function module(): Module
    {
        return $this->module;
    }

    public function name(): string
    {
        return $this->module->getName();
    }

    public function alias(): string
    {
        return $this->module->getLowerName();
    }

    public function path(): string
    {
        return $this->module->getPath();
    }

    public function dependencies(): array
    {
        $requires = $this->module->get('requires') ?? $this->module->get('depends') ?? [];

        if (! is_array($requires)) {
            return [];
        }

        return array_values($requires);
    }

    public function manifest(): array
    {
        return $this->module->json()->getAttributes();
    }
}
