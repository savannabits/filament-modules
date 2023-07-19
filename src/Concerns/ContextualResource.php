<?php

namespace Savannabits\FilamentModules\Concerns;

use Filament\Facades\Filament;
use Savannabits\FilamentModules\FilamentModules;

trait ContextualResource
{
    public static function getRouteBaseName(): string
    {
        $slug = static::getSlug();

        return Filament::currentContext().".resources.{$slug}";
    }
    public static function getModuleName() {
        return \Str::of(Filament::currentContext())->before('-')->studly();
    }
    public static function hasAccess() {
        return FilamentModules::hasAuthorizedAccess(Filament::currentContext());
    }
    public static function bootContextualResource() {
        abort_if(!static::hasAccess(),403,"You don't have access to the ".static::getModuleName()." module.");
    }
}
