<?php

namespace Coolsam\Modules;

use Filament\Contracts\Plugin;
use Filament\Panel;

class Modules implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }
    public function getId(): string
    {
        return 'nwidart-modules';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
