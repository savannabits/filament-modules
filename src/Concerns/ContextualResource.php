<?php

namespace Savannabits\FilamentModules\Concerns;

use Filament\Facades\Filament;

trait ContextualResource
{
    public static function getRouteBaseName(): string
    {
        $slug = static::getSlug();

        return Filament::currentContext().".resources.{$slug}";
    }
}
