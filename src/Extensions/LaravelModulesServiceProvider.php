<?php

namespace Coolsam\Modules\Extensions;

use Nwidart\Modules\LaravelModulesServiceProvider as BaseModulesServiceProvider;

class LaravelModulesServiceProvider extends BaseModulesServiceProvider
{
    public function register(): void
    {
        $this->registerPanels();
        parent::register();
    }
    public function registerPanels(): void
    {
        // Override this to do anything during registration
    }
}
