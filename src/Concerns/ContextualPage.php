<?php

namespace Savannabits\FilamentModules\Concerns;

use Filament\Facades\Filament;
use Savannabits\FilamentModules\FilamentModules;

trait ContextualPage
{
    public static function getRouteName(): string
    {
        $slug = static::getSlug();

        return Filament::currentContext() . ".pages.{$slug}";
    }

    public static function getModuleName()
    {
        return \Str::of(Filament::currentContext())->before('-')->studly();
    }

    public static function hasAccess()
    {
        return FilamentModules::hasAuthorizedAccess(Filament::currentContext());
    }

    public static function bootContextualPage()
    {
        abort_if(!static::hasAccess(), 403, "You don't have access to the " . static::getModuleName() . " module.");
    }
}
