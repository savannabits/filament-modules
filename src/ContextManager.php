<?php

namespace Savannabits\FilamentModules;

use Filament\FilamentManager;
use Illuminate\Contracts\Auth\Guard;

class ContextManager extends FilamentManager
{
    protected static ?string $config = null;

    public function __construct($config)
    {
        static::$config = $config;
    }

    public static function getAuth(): ?Guard
    {
        return static::$config ? auth()->guard(config(static::$config.'.auth.guard')) : null;
    }

    public function auth(): Guard
    {
        return static::getAuth() ?? auth()->guard(config('filament.auth.guard'));
    }
}
