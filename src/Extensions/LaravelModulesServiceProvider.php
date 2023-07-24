<?php

namespace Coolsam\FilamentModules\Extensions;

use Illuminate\Support\Facades\Log;
use Nwidart\Modules\LaravelModulesServiceProvider as BaseModulesServiceProvider;

class LaravelModulesServiceProvider extends BaseModulesServiceProvider
{
    public function register(): void
    {
        $this->registerPanels();
        parent::register();
        Log::info('Registered Modules');
    }

    public function registerPanels(): void
    {
        // Override this to do anything during registration
    }
}
